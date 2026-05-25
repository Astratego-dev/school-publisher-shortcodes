# School Publisher Shortcodes

WordPress plugin for building school literature books from a managed catalog of grades, authors, literary works, and plays.

## Shortcodes

- `[school_book_builder]` - logged-in school coordinator interface for choosing a grade, plays, literary works, hardcover option, and saving a book request.
- `[school_book_request]` - public request summary shortcode for future approval/order pages.

## Admin Workflow

1. Open **בונה ספרים** in the WordPress admin.
2. Add grades, authors, categories, literary works, and plays.
3. Mark works and plays as active so they appear in the builder.
4. Set pricing under **הגדרות תמחור**.
5. Create a WordPress page and place `[school_book_builder]` inside it.

## Pricing

The plugin supports:

- Base book price.
- Per-page price.
- Hardcover price.
- Per-play price.
- Optional fixed global price.
- Optional fixed user price by user ID or email.
