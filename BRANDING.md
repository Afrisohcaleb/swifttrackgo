# Branding customization

The interface is intentionally neutral and uses a text-based logo mark. Replace starter copy and visuals with approved company identity before launch.

## Colors and typography

Edit the CSS custom properties at the top of `assets/css/style.css`:

- `--brand`: primary color used by buttons and highlights.
- `--brand-dark`: darker link and hover color.
- `--accent`: secondary highlight color.
- `--ink`: main text color.
- `--paper`: soft page background.
- `--line`: borders and separators.

The starter uses the system font stack. Change the `font` declaration on the `body` rule to use your selected font. Recheck color contrast after changing colors.

## Image replacement locations

Add approved assets to these folders, then update the relevant markup or CSS to reference them:

- `assets/logos/`: primary, secondary, and dashboard logos.
- `assets/images/`: service, company, shipment, and delivery images or illustrations.
- `assets/backgrounds/`: hero, page, and sign-in backgrounds.

The favicon is not wired in yet. Add your approved file and a `<link rel="icon">` element in `includes/header.php` and `includes/admin_header.php`.

## Company details

Replace starter copy in `index.php`, `about.php`, `services.php`, and `includes/footer.php`. The optional `database/seed.sql` inserts sample setting values for future settings support. Public content is currently static and there is no admin settings editor or image-upload screen.

Do not upload untrusted files into a public directory. If an upload feature is added, validate content by MIME and image decoding, enforce a size limit, generate server-side filenames, and prevent script execution in the destination folder.
