# RE:Access Plugin Testing Guide

## Plugin Structure Created

```
re-access/
├── re-access.php      # Main plugin file (v1.0.0)
├── composer.json      # Composer configuration
├── composer.lock      # Dependency lock file
└── vendor/           # Composer dependencies (included in distribution)
    ├── autoload.php
    ├── composer/
    └── yahnis-elsts/plugin-update-checker/
```

## Key Features Implemented

### 1. WordPress Plugin Header
- Plugin Name: RE:Access
- Version: 1.0.0
- Requires PHP: 8.1+
- Requires WordPress: 6.0+
- Text Domain: re-access

### 2. Activation Hook
- Saves version '1.0.0' to WordPress options table under key 're-access_version'
- Function: `re_access_activate()`

### 3. Admin Menu
- Menu title: "RE:Access"
- Position: 79 (above Settings which is at 80)
- Icon: dashicons-chart-line
- Capability required: 'manage_options'
- Callback: `re_access_dashboard_page()`

### 4. Plugin Update Checker
- GitHub repository: https://github.com/TaniyanR/RE-Access
- Branch: main
- Release assets enabled
- Function: `re_access_init_update_checker()`

### 5. Dashboard Page
- Simple welcome message showing plugin version
- Located in admin menu

## Manual Testing Instructions

### Prerequisites
1. WordPress 6.0+ installation
2. PHP 8.1+ environment
3. Admin access to WordPress

### Testing Steps

#### 1. Install Plugin
```bash
# Option A: Copy to WordPress plugins directory
cp -r re-access /path/to/wordpress/wp-content/plugins/

# Option B: Create zip for upload
cd /home/runner/work/RE-Access/RE-Access
zip -r re-access.zip re-access/
# Upload via WordPress admin: Plugins → Add New → Upload Plugin
```

#### 2. Activate Plugin
1. Go to WordPress admin → Plugins
2. Find "RE:Access" in the list
3. Click "Activate"
4. **Expected Result**: Plugin activates without errors

#### 3. Verify Activation Hook
```sql
-- Check WordPress database
SELECT option_name, option_value FROM wp_options WHERE option_name = 're-access_version';
-- Expected: option_value = '1.0.0'
```

Or via PHP:
```php
echo get_option('re-access_version');
// Expected output: 1.0.0
```

#### 4. Verify Admin Menu
1. After activation, check WordPress admin left sidebar
2. **Expected Result**: "RE:Access" menu item appears above "Settings"
3. Menu should have a chart-line icon

#### 5. Test Dashboard Page
1. Click on "RE:Access" menu item
2. **Expected Result**: Page displays:
   - Title: "RE:Access Dashboard"
   - Message: "Welcome to RE:Access version 1.0.0"

#### 6. Verify Auto-Update Setup (Admin Only)
1. Go to Dashboard → Updates or Plugins page
2. Plugin Update Checker should be initialized
3. It will check GitHub for updates (currently placeholder)
4. **Note**: No updates will be available until releases are published

#### 7. Verify Vendor Dependencies
```bash
# Check that vendor directory exists and contains update checker
ls -la wp-content/plugins/re-access/vendor/
# Should show: autoload.php, composer/, yahnis-elsts/
```

## Code Quality Checks

### PHP Syntax
```bash
php -l re-access.php
# Should return: No syntax errors detected
```

### Check for Old Names
```bash
grep -ri "access-z\|access_z\|access z" re-access/
# Should return: no results (clean)
```

### Verify PHP Version Requirement
```bash
php -v
# Should be 8.1 or higher
```

## Security Checks

### 1. Direct Access Protection
- File begins with: `if (!defined('WPINC')) { die; }`
- Prevents direct PHP file access

### 2. Capability Checks
- Admin menu requires 'manage_options' capability
- Only administrators can access

### 3. Output Escaping
- Uses `esc_html__()` for translations
- Dashboard output is properly escaped

## Deactivation Test
1. Go to Plugins page
2. Deactivate RE:Access
3. **Expected Result**: Deactivates cleanly
4. Menu item disappears from admin sidebar
5. Option 're-access_version' remains in database (normal behavior)

## Uninstall Test
1. Deactivate the plugin
2. Delete the plugin
3. **Expected Result**: Files removed successfully
4. **Note**: Version option will remain unless uninstall hook is added in future

## Known Limitations (By Design)
- No database tables created yet (skeleton only)
- No access tracking implemented yet
- Dashboard shows minimal content
- No uninstall hook (will be added when DB tables are created)
- Update checker uses placeholder URL (will work when releases are published)

## Success Criteria
✓ Plugin activates without errors
✓ Version 1.0.0 is saved to wp_options
✓ Admin menu appears at position 79
✓ Dashboard page is accessible and displays correctly
✓ No PHP warnings or errors in debug log
✓ No traces of old plugin names (access-z, az, Access Z)
✓ Vendor directory is included and functional
✓ Plugin update checker is initialized

## Next Steps (Future Development)
- Implement access tracking functionality
- Create database tables for data storage
- Add migration framework for version upgrades
- Build dashboard with KPIs and graphs
- Implement site registration feature
- Add ranking display functionality
- Create RSS/link slot system
- Add uninstall hook for cleanup
