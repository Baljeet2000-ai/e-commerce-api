<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class OrderController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * @OA\Get(
     *     path="/api/orders",
     *     tags={"Ordenes"},
     *     summary="Historial de compras del usuario autenticado",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Listado de ordenes del usuario")
     * )
     */
    public function index(Request $request)
    {
        try {
            $orders = $request->user()
                ->orders()
                ->with(['items.product', 'payment'])
                ->latest()
                ->get();

            return response()->json([
                'data' => $orders,
            ]);
        } catch (\Exception $error) {
            return response()->json([
                'message' => $error->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders/{id}",
     *     tags={"Ordenes"},
     *     summary="Ver el detalle de una orden propia",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Detalle de la orden"),
     *     @OA\Response(response=404, description="Orden no encontrada")
     * )
     */
    public function show(Request $request, string $id)
    {
        try {
            // Solo puede ver ordenes que le pertenecen a el mismo
            $order = $request->user()
                ->orders()
                ->with(['items.product', 'payment'])
                ->findOrFail($id);

            return response()->json([
                'data' => $order,
            ]);
        } catch (\Exception $error) {
            return response()->json([
                'message' => $error->getMessage(),
            ], 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/orders",
     *     tags={"Ordenes"},
     *     summary="Crear una orden y procesar el pago con Stripe",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"items","payment_method"},
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(property="quantity", type="integer", example=2)
     *                 )
     *             ),
     *             @OA\Property(property="payment_method", type="string", example="pm_card_visa")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Orden creada y pago procesado"),
     *     @OA\Response(response=402, description="El pago fue rechazado"),
     *     @OA\Response(response=422, description="Error de validacion o stock insuficiente")
     * )
     */
    public function store(StoreOrderRequest $request)
    {
        $data = $request->validated();

        try {
            // 1. Creamos la orden y sus items dentro de una transaccion.
            // Si algo falla aqui (ej. stock insuficiente) no se guarda nada.
            $order = DB::transaction(function () use ($data, $request) {
                $total = 0;
                $lines = [];

                foreach ($data['items'] as $item) {
                    $product = Product::findOrFail($item['product_id']);

                    if ($product->stock < $item['quantity']) {
                        throw new \Exception("Not enough stock for product: {$product->name}");
                    }

                    $lines[] = ['product' => $product, 'quantity' => $item['quantity']];
                    $total += $product->price * $item['quantity'];
                }

                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'total' => $total,
                    'status' => 'pending',
                ]);

                foreach ($lines as $line) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $line['product']->id,
                        'quantity' => $line['quantity'],
                        'price' => $line['product']->price,
                    ]);

                    $line['product']->decrement('stock', $line['quantity']);
                }

                return $order;
            });

            // 2. Procesamos el pago con Stripe (esto ya es una llamada externa,
            // por eso va fuera de la transaccion de base de datos)
            $paymentIntent = PaymentIntent::create([
                'amount' => (int) round($order->total * 100), // Stripe espera el monto en centavos
                'currency' => 'usd',
                'payment_method' => $data['payment_method'],
                'confirm' => true,
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never',
                ],
            ]);

            $status = $paymentIntent->status === 'succeeded' ? 'paid' : 'failed';
            $order->update(['status' => $status]);
            $order->load('items.product', 'payment');

            Payment::create([
                'order_id' => $order->id,
                'stripe_payment_intent_id' => $paymentIntent->id,
                'amount' => $order->total,
                'currency' => 'usd',
                'status' => $paymentIntent->status,
                'payment_method' => $data['payment_method'],
            ]);

            if ($status === 'failed') {
                // Devolvemos el stock reservado ya que el pago no se completo
                foreach ($order->items as $item) {
                    $item->product->increment('stock', $item->quantity);
                }

                return response()->json([
                    'message' => 'Payment was not completed',
                    'data' => $order->fresh(['items.product', 'payment']),
                ], 402);
            }

            return response()->json([
                'message' => 'Order created and paid successfully',
                'data' => $order->fresh(['items.product', 'payment']),
            ], 201);

        } catch (ApiErrorException $error) {
            // Errores propios de Stripe (tarjeta rechazada, fondos insuficientes, etc.)
            if (isset($order)) {
                $order->load('items.product');

                foreach ($order->items as $item) {
                    $item->product->increment('stock', $item->quantity);
                }

                $order->update(['status' => 'failed']);
            }

            return response()->json([
                'message' => 'Stripe error: '.$error->getMessage(),
            ], 402);

        } catch (\Exception $error) {
            return response()->json([
                'message' => $error->getMessage(),
            ], 422);
        }
    }
}
