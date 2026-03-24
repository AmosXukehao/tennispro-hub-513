# TennisPro Hub Capstone — WordPress Child Theme

ICTWEB 513 E-Commerce Capstone: **WordPress as base CMS**, all customizations in this child theme.

## Installation

1. Upload this folder to `wp-content/themes/` so you have `wp-content/themes/tennispro-capstone/`.
2. Ensure parent theme **Twenty Twenty-Four** is installed (or change `Template:` in `style.css` to your parent theme folder name).
3. In WordPress admin → Appearance → Themes, activate **TennisPro Hub Capstone**.

On activation, the theme will:
- Create tables: `wp_orders`, `wp_order_items`, `wp_support_tickets`, `wp_job_applications`, `wp_forum_posts`
- Create `wp-content/uploads/cv_uploads/` and add `.htaccess` to disable PHP execution
- Copy `products.json` to `wp-content/uploads/products.json` on first use (or you can upload it manually)
- Seed 20 sample forum posts if the forum table is empty

## Pages to create

Create the following pages and assign the template shown:

| Slug         | Template                    |
|-------------|-----------------------------|
| products    | Products (Capstone)         |
| cart        | Cart / Order (Capstone)     |
| checkout    | Checkout Login (Capstone)   |
| payment     | Payment (Capstone)          |
| support     | Customer Support (Capstone)  |
| about       | About (Capstone)            |
| forum       | Discussion Forum (Capstone)  |
| jobs        | Recruitment / Careers (Capstone) |
| customer-list | Customer List (Capstone)  |
| register    | (FluentCRM form shortcode)   |

Set **Settings → Reading** to a static front page and choose your **Home** page (rendered by `front-page.php`).

## API

- **GET** `https://yoursite.com/wp-json/custom/v1/products` — returns all products from `wp-content/uploads/products.json`.

## Admin

- **Products JSON** in the admin menu: CRUD for `wp-content/uploads/products.json` (admin only).

## FluentCRM

- Use FluentCRM (and Fluent Forms) for registration with **≥8 fields**. Checkout login validates **Email + Phone** against FluentCRM subscribers. Customer List template shows subscribers (Name, Email, Phone); ensure **≥30** for submission.

See project root **CAPSTONE_第二点完成指南.md** for the full setup guide.
