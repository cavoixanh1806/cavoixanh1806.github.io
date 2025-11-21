# Login System Setup

## Database Configuration

1. **Update database credentials** in `config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'cavoixan_login');
   define('DB_USER', 'cavoixan_login');
   define('DB_PASS', 'Kafk80022azpm@');
   ```

2. **Create database table** by running the SQL in `config/schema.sql`:
   ```sql
   CREATE TABLE IF NOT EXISTS `users` (
     `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
     `username` VARCHAR(100) NOT NULL UNIQUE,
     `email` VARCHAR(255) NOT NULL UNIQUE,
     `password_hash` VARCHAR(255) NOT NULL,
     `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
     `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
     PRIMARY KEY (`id`),
     INDEX `idx_email` (`email`),
     INDEX `idx_username` (`username`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```

## API Endpoints

- `POST /login/api/login.php` - User login (username or email)
- `POST /login/api/register.php` - User registration (username + email required)
- `POST /login/api/logout.php` - Logout
- `GET /login/api/me.php` - Get current user info

## Testing

1. Visit `/login` to test login page
2. Visit `/login/register` to test registration page
3. Check browser console for any errors
4. Check PHP error logs if API calls fail

