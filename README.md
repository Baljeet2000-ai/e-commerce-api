# E-commerce API — Guía de instalación

## 1. Instalar paquetes

```bash
composer require stripe/stripe-php
composer require darkaonline/l5-swagger
```

## 2. Publicar configuración de Swagger

```bash
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

Esto crea `config/l5-swagger.php`.

## 3. Variables de entorno (`.env`)

Agrega:

```env
STRIPE_KEY=pk_test_xxxxxxxxxxxx
STRIPE_SECRET=sk_test_xxxxxxxxxxxx

L5_SWAGGER_CONST_HOST=http://localhost:8000/api
```

Usa tus llaves de prueba de Stripe (las encuentras en el dashboard de Stripe, modo Test).

## 4. Registrar las credenciales de Stripe en `config/services.php`

Abre tu `config/services.php` existente y agrega dentro del array que retorna:

```php
'stripe' => [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
],
```
## 5. Migraciones y seeders

```bash
php artisan migrate:fresh --seed
```

Esto crea las tablas `products`, `orders`, `order_items`, `payments` (además de las que ya
tenías) y las llena con datos de ejemplo, incluyendo 21 productos.

## 6. Generar la documentación Swagger

```bash
php artisan l5-swagger:generate
```

Disponible en: `http://localhost:8000/api/documentation`

## 7. Probar el flujo de compra con Stripe (modo test)

Usa el Payment Method ID de prueba `pm_card_visa` en el body de `POST /api/orders`:

```json
{
    "items": [
        {"product_id": 1, "quantity": 2}
    ],
    "payment_method": "pm_card_visa"
}
```

Para simular un pago rechazado, Stripe también ofrece IDs de prueba para tarjetas
declinadas (revisa la documentación de Stripe: "Testing" → "Payment methods").

## Endpoints principales

| Método | Ruta                     | Protegido | Descripción                          |
|--------|--------------------------|-----------|---------------------------------------|
| POST   | /api/register            | No        | Registro de cliente                   |
| POST   | /api/login               | No        | Login, devuelve token                 |
| POST   | /api/logout              | Sí        | Revoca el token actual                |
| GET    | /api/products            | No        | Catálogo público                      |
| GET    | /api/products/{id}       | No        | Detalle de un producto                |
| POST   | /api/products            | Sí        | Crear producto                        |
| PUT    | /api/products/{id}       | Sí        | Actualizar producto                   |
| DELETE | /api/products/{id}       | Sí        | Eliminar producto (soft delete)       |
| PUT    | /api/products/{id}/restore | Sí      | Restaurar producto eliminado          |
| POST   | /api/orders              | Sí        | Crear orden + procesar pago Stripe    |
| GET    | /api/orders              | Sí        | Historial de compras del usuario      |
| GET    | /api/orders/{id}         | Sí        | Detalle de una orden propia           |
