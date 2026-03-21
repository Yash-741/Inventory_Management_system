# Inventory Management System

A lightweight web-based inventory dashboard with login/session authentication and a SQLite-backed PHP API.

## Header
- Project: Inventory Management System
- Stack: HTML, CSS, JavaScript, PHP, SQLite
- Data Storage: SQLite (`includes/inventory.sqlite`) seeded from `includes/db.json` and `includes/users.json`
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
- `includes/sqlite.php`: SQLite connection, schema bootstrap, and seed import.
- `includes/config.php`: MySQL config file (present in project, but current API flow uses JSON storage).

### Data Files
- `includes/inventory.sqlite`: Generated SQLite database file for local runs.
- `includes/db.json`: Seed product and inventory data used to initialize SQLite.
- `includes/users.json`: Seed user credentials and roles used to initialize SQLite.
- `database/schema.sql`: Legacy SQL schema reference.

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

## Vercel Deploy
- This project includes `vercel.json` and PHP function wrappers in `api/` for Vercel.
- Login works on Vercel through `api/auth.php`.
- On Vercel, the SQLite file is created in temporary runtime storage when the packaged filesystem is not writable.
- That means SQLite changes are not durable across cold starts or redeploys on Vercel.
- For persistent production data on Vercel, move SQLite to persistent storage or replace it with a hosted database.

## Notes
- No manual DB setup is required for normal usage; SQLite is created automatically on first run.
- Replace demo/plain-text passwords in `users.json` with hashed passwords for production.
- Restrict CORS and disable debug-style behavior before deployment.
