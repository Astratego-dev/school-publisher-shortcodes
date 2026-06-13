# School Publisher Shortcodes

WordPress plugin for building school literature books from a managed catalog of grades, authors, literary works, and plays.

## Shortcodes

- `[school_book_builder]` - logged-in school coordinator interface for choosing a grade, plays, literary works, hardcover option, and saving a book request.
- `[school_book_request]` - public request summary shortcode for future approval/order pages.
- `[school_publisher_home]` - marketing homepage for literature coordinators.
- `[school_publisher_amir]` - dedicated proposal page for Amir Hasson.
- `[school_literature_activities]` - coming-soon activities page for Antigone learning games and quests.

The homepage shortcode content can be edited in **בונה ספרים → עמוד הבית**. Default values match the built-in landing page.

## Admin Workflow

1. Open **בונה ספרים** in the WordPress admin.
2. Add grades, authors, categories, literary works, and plays.
3. Mark works and plays as active so they appear in the builder.
4. Set pricing under **הגדרות תמחור**.
5. Create a WordPress page and place `[school_book_builder]` inside it.

## Catalog Import

Use **בונה ספרים → ייבוא מאגר תוכן** to paste CSV content.

Example:

```csv
title,author,grade,category,pages,price,required,active
הכניסיני תחת כנפך,חיים נחמן ביאליק,כיתה י,שירה,2,,1,1
```

## Pricing

The plugin supports:

- Base book price.
- Base page count.
- Lower and upper page thresholds that keep the base price unchanged.
- Per-page price.
- Hardcover price.
- Per-play price.
- Optional fixed global price.
- Optional fixed user price by user ID or email.

## Saved Books and Approval

Coordinators can open a previously saved book from the builder and save it as a new copy for a new year or a revised version.

Every saved book starts as `new` and is not publicly visible until the owner approves it in the admin request screen.

## Live Literature Guidelines

The builder includes live Ministry of Education guideline checks for middle school and high school literature programs. The checklist updates as coordinators choose works and plays, but it does not block saving a book request.
