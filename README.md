# Inventory Management System

A lightweight web-based inventory dashboard with login/session authentication and JSON-backed APIs.

## Header
- Project: Inventory Management System
- Stack: HTML, CSS, JavaScript, PHP
- Data Storage: JSON files (`includes/db.json`, `includes/users.json`)
- Entry Pages: `login.html`, `index.html`

## Important Components

### Frontend
- `login.html`: Login UI and authentication entry point.
- `index.html`: Main inventory dashboard UI.
- `assets/css/login.css`: Login page styles.
- `assets/css/styles.css`: Dashboard styles and responsive layout.
- `assets/js/login.js`: Login flow, session checks, and logout integration.
- `assets/js/app.js`: Inventory rendering, stats updates, and quantity update actions.

### Backend
- `includes/auth.php`: Login, logout, session check, token generation, and login attempt logging.
- `includes/api.php`: Inventory API (get all items, stats, single product, update quantity).
- `includes/config.php`: MySQL config file (present in project, but current API flow uses JSON storage).

### Data Files
- `includes/db.json`: Product and inventory data source used by `api.php`.
- `includes/users.json`: User credentials and roles used by `auth.php`.
- `includes/login_attempts.log`: Authentication activity/failed-attempt logs.
- `database/schema.sql`: SQL schema reference.

## API Endpoints

### Authentication (`includes/auth.php`)
- `POST includes/auth.php` -> Login (`username`, `password`, optional `remember`)
- `GET includes/auth.php?action=check_session` -> Check active session
- `GET includes/auth.php?action=logout` -> Logout current user

### Inventory (`includes/api.php`)
- `GET includes/api.php?action=get_all_inventory` -> Inventory list + stats
- `GET includes/api.php?action=get_inventory_stats` -> Stats only
- `GET includes/api.php?action=get_product&product_id=1` -> Single product details
- `POST includes/api.php?action=update_quantity` -> Update quantity (`product_id`, `quantity`)

## Local Run
1. Place the project in your PHP server directory (for example, XAMPP `htdocs`).
2. Start Apache (and MySQL only if you need `config.php` for DB-based work).
3. Open:
   - `http://localhost/<project-folder>/login.html`
   - `http://localhost/<project-folder>/index.html`

## Notes
- Current implementation is file-based (JSON), so no DB setup is required for normal usage.
- Replace demo/plain-text passwords in `users.json` with hashed passwords for production.
- Restrict CORS and disable debug-style behavior before deployment.
