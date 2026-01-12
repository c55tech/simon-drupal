# SIMON Drupal Module

Drupal module for integrating with the SIMON monitoring system.

## Installation

### Via Composer (Recommended)

Add the repository using Composer commands:

```bash
# Add the repository
composer config repositories.simon-drupal vcs git@github.c55:c55tech/simon-drupal.git

# Install the module
composer require simon/integration:dev-main

# Enable the module
drush en simon
```

Or via the admin UI: Extend → Install → SIMON Integration

Alternatively, you can manually add the repository to your `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.c55:c55tech/simon-drupal.git"
    }
  ],
  "require": {
    "simon/integration": "dev-main"
  }
}
```

Then install:

```bash
composer require simon/integration:dev-main
drush en simon
```

### Manual Installation

1. Copy this module to your Drupal site's `modules/custom/` directory:
   ```bash
   cp -r simon-drupal /path/to/drupal/modules/custom/simon
   ```

2. Enable the module:
   ```bash
   drush en simon
   ```
   Or via the admin UI: Extend → Install → SIMON Integration

## Configuration

### Step 1: Configure API URL

1. Navigate to: **Configuration → Web services → SIMON Settings**
   (`/admin/config/services/simon`)
2. Enter the SIMON API base URL (e.g., `http://localhost:3000`)
3. Configure cron settings if desired
4. Save

### Step 2: Create Client

1. Navigate to: **Configuration → Web services → SIMON Client Configuration**
   (`/admin/config/services/simon/client`)
2. Fill in:
   - Client Name (required)
   - Contact Name (optional)
   - Contact Email (optional)
3. Click **Create/Update Client**
4. The Client ID will be saved automatically

### Step 3: Create Site

1. Navigate to: **Configuration → Web services → SIMON Site Configuration**
   (`/admin/config/services/simon/site`)
2. Fill in:
   - Site Name
   - Site URL
   - External ID (optional)
   - Auth Token (optional)
3. Click **Create/Update Site**
4. The Site ID will be saved automatically

### Step 4: Test Submission

1. On the Site Configuration page, click **Submit Data Now** to test
2. Or use Drush: `drush simon:submit`

## Cron Integration

If enabled in settings, the module will automatically submit site data when Drupal cron runs.

To configure:
1. Go to SIMON Settings
2. Check "Enable automatic submission via cron"
3. Select the desired interval
4. Save

## Drush Command

Submit site data manually via Drush:

```bash
drush simon:submit
```

Or use the alias:

```bash
drush simon-submit
```

## What Data is Collected

The module collects and submits:

- **Core**: Drupal version and update status
- **Log Summary**: Error/warning counts from watchdog
- **Environment**: PHP version, memory limits, database info, web server
- **Extensions**: All installed modules with versions
- **Themes**: All installed themes with versions

## Troubleshooting

- Check Drupal logs: **Reports → Recent log messages**
- Verify API URL is accessible from your Drupal server
- Ensure Client ID and Site ID are configured
- Test with Drush command: `drush simon:submit`

















