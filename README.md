<<<<<<< HEAD
# oriflame-coast-backend
=======
# Oriflame Coast Backend API

Laravel backend API for the Oriflame Coast Region e-commerce platform.

## Requirements

- PHP 8.1 or higher
- Composer
- MySQL 8.0+ or MariaDB 10.3+
- Node.js (for frontend integration)

## Installation

1. **Install PHP dependencies:**
   ```bash
   composer install
   ```

2. **Environment Configuration:**
   ```bash
   cp .env.example .env
   ```
   
   Update the `.env` file with your database credentials:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=oriflame_coast
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

3. **Generate Application Key:**
   ```bash
   php artisan key:generate
   ```

4. **Database Setup:**
   
   Create the database:
   ```sql
   CREATE DATABASE oriflame_coast CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
   
   Run migrations:
   ```bash
   php artisan migrate
   ```

5. **Seed Database (Optional):**
   ```bash
   php artisan db:seed
   ```

6. **Start Development Server:**
   ```bash
   php artisan serve
   ```
   
   The API will be available at `http://localhost:8000`

## Database Configuration

### MySQL/MariaDB Setup

The application is configured to use MySQL/MariaDB by default. To switch between them:

**For MySQL:**
```env
DB_CONNECTION=mysql
```

**For MariaDB:**
```env
DB_CONNECTION=mariadb
```

### Database Schema

The application includes the following tables:
- `users` - User accounts and profiles
- `products` - Product catalog
- `stocks` - Inventory management
- `orders` - Customer orders
- `order_items` - Order line items

## API Endpoints

### Authentication
- `POST /api/register` - User registration
- `POST /api/login` - User login
- `POST /api/logout` - User logout (authenticated)
- `GET /api/user` - Get current user (authenticated)

### Products
- `GET /api/products` - List all products
- `GET /api/products/{id}` - Get product details
- `POST /api/products` - Create product (admin)
- `PUT /api/products/{id}` - Update product (admin)
- `DELETE /api/products/{id}` - Delete product (admin)

### Orders
- `GET /api/my-orders` - Get user orders (authenticated)
- `POST /api/orders` - Create new order (authenticated)

### User Profile
- `GET /api/profile` - Get user profile (authenticated)
- `PUT /api/profile` - Update user profile (authenticated)

## CORS Configuration

The API is configured to accept requests from:
- `http://localhost:3000` (Next.js frontend)
- `http://127.0.0.1:3000`

To add additional origins, update the `config/cors.php` file.

## Authentication

The API uses Laravel Sanctum for authentication. The frontend should:

1. Make a GET request to `/sanctum/csrf-cookie` to initialize CSRF protection
2. Include credentials in all subsequent requests
3. Use the `/api/login` endpoint for authentication

## Production Deployment

1. Set `APP_ENV=production` in `.env`
2. Set `APP_DEBUG=false` in `.env`
3. Configure your production database credentials
4. Run `php artisan config:cache`
5. Run `php artisan route:cache`
6. Set up proper web server configuration (Apache/Nginx)

## Troubleshooting

### Database Connection Issues
- Verify MySQL/MariaDB is running
- Check database credentials in `.env`
- Ensure the database exists
- Verify PHP has the required database extensions

### CORS Issues
- Check the `config/cors.php` configuration
- Verify the frontend URL is in the allowed origins
- Ensure credentials are being sent with requests

### Authentication Issues
- Verify Sanctum configuration in `config/sanctum.php`
- Check that the frontend is making requests to `/sanctum/csrf-cookie`
- Ensure cookies are being sent with requests
>>>>>>> 666ee9b (Initial commit: Laravel backend)
