<?php
/**
 * Single-Shot Webapp Setup Wizard for Society HubX
 *
 * Automates WordPress installation, configures database credentials,
 * activates the society management plugin, and sets up pretty permalinks.
 *
 * @author Vishwas R
 */

// Disable error display to prevent outputting raw PHP notices before UI renders
ini_set('display_errors', 0);
error_reporting(E_ALL);

define('WP_SETUP_WIZARD', true);

// Path definitions
$root_dir = dirname(__FILE__);
$config_path = $root_dir . '/wp-config.php';
$sample_config_path = $root_dir . '/wp-config-sample.php';

// Helper: Check if WordPress is already installed
$is_installed = false;
$db_error_msg = '';

if (file_exists($config_path)) {
    // Attempt to load WordPress environment
    try {
        // Disable redirection in wp-load.php by stating we are installing
        if (!defined('WP_INSTALLING')) {
            define('WP_INSTALLING', true);
        }
        
        // Suppress errors during load
        ob_start();
        include_once $root_dir . '/wp-load.php';
        ob_end_clean();
        
        if (defined('ABSPATH')) {
            global $wpdb;
            // Check if tables are present and if WordPress is installed
            $suppress = $wpdb->suppress_errors(true);
            $tables = $wpdb->get_var("SHOW TABLES LIKE '" . $wpdb->prefix . "users'");
            $wpdb->suppress_errors($suppress);
            
            if ($tables) {
                include_once ABSPATH . 'wp-admin/includes/upgrade.php';
                if (function_exists('is_blog_installed') && is_blog_installed()) {
                    $is_installed = true;
                }
            }
        }
    } catch (Exception $e) {
        // Suppress and continue to installation form
    }
}

// Helper: Standalone salt generator
function shubx_generate_salt() {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_ []{}<>~`+=,.;:/?|';
    $salt = '';
    $len = strlen($chars);
    for ($i = 0; $i < 64; $i++) {
        $salt .= $chars[rand(0, $len - 1)];
    }
    return addslashes($salt);
}

// Handler: Destructive Reset & Reinstall
if (isset($_POST['action']) && $_POST['action'] === 'reset_reinstall') {
    if (file_exists($config_path)) {
        try {
            if (!defined('WP_INSTALLING')) {
                define('WP_INSTALLING', true);
            }
            include_once $root_dir . '/wp-load.php';
            if (defined('ABSPATH')) {
                global $wpdb;
                // Suppress errors and drop all tables with current prefix
                $wpdb->suppress_errors(true);
                
                // Drop core WP tables
                $core_tables = array(
                    'commentmeta', 'comments', 'links', 'options', 'postmeta',
                    'posts', 'termmeta', 'terms', 'term_relationships',
                    'term_taxonomy', 'usermeta', 'users'
                );
                foreach ($core_tables as $tbl) {
                    $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}{$tbl}`");
                }

                // Drop Society HubX custom tables matching Prefix + 'society_hubx_'
                $shubx_tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}society_hubx_%'");
                if (is_array($shubx_tables)) {
                    foreach ($shubx_tables as $table) {
                        $wpdb->query("DROP TABLE IF EXISTS `$table`");
                    }
                }
            }
        } catch (Exception $e) {
            // Proceed to file deletion
        }
        
        // Delete wp-config.php
        @unlink($config_path);
    }
    
    // Redirect to start fresh
    header('Location: setup.php');
    exit;
}

// Handler: Run Installation Process
$install_error = '';
$install_success = false;

if (isset($_POST['action']) && $_POST['action'] === 'run_install') {
    $db_host = sanitize_input($_POST['db_host'] ?? 'localhost');
    $db_name = sanitize_input($_POST['db_name'] ?? '');
    $db_user = sanitize_input($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass'] ?? '';
    $db_prefix = sanitize_input($_POST['db_prefix'] ?? 'wp_');
    
    $site_title = sanitize_input($_POST['site_title'] ?? 'Society HubX Community');
    $admin_user = sanitize_input($_POST['admin_user'] ?? 'admin');
    $admin_pass = $_POST['admin_pass'] ?? '';
    $admin_email = filter_var($_POST['admin_email'] ?? '', FILTER_VALIDATE_EMAIL);

    if (empty($db_name) || empty($db_user)) {
        $install_error = 'Database name and database user are required.';
    } elseif (empty($admin_pass) || strlen($admin_pass) < 6) {
        $install_error = 'Admin password must be at least 6 characters long.';
    } elseif (!$admin_email) {
        $install_error = 'Please enter a valid administrator email address.';
    } else {
        // 1. Verify Database Connection
        $conn = @mysqli_connect($db_host, $db_user, $db_pass);
        if (!$conn) {
            $install_error = 'Failed to connect to database server: ' . mysqli_connect_error();
        } else {
            // Attempt to create database if not exists
            $db_selected = @mysqli_select_db($conn, $db_name);
            if (!$db_selected) {
                $created = @mysqli_query($conn, "CREATE DATABASE `" . mysqli_real_escape_string($conn, $db_name) . "`");
                if (!$created) {
                    $install_error = 'Database does not exist and could not be created automatically. Details: ' . mysqli_error($conn);
                } else {
                    $db_selected = true;
                }
            }
            
            if ($db_selected) {
                @mysqli_close($conn);
                
                // 2. Generate wp-config.php
                if (!file_exists($sample_config_path)) {
                    $install_error = 'wp-config-sample.php is missing. Please ensure all WordPress core files are uploaded.';
                } else {
                    $config_content = file_get_contents($sample_config_path);
                    
                    // Replace standard DB constants
                    $config_content = str_replace("define( 'DB_NAME', 'database_name_here' );", "define( 'DB_NAME', '$db_name' );", $config_content);
                    $config_content = str_replace("define( 'DB_USER', 'username_here' );", "define( 'DB_USER', '$db_user' );", $config_content);
                    $config_content = str_replace("define( 'DB_PASSWORD', 'password_here' );", "define( 'DB_PASSWORD', '$db_pass' );", $config_content);
                    $config_content = str_replace("define( 'DB_HOST', 'localhost' );", "define( 'DB_HOST', '$db_host' );", $config_content);
                    $config_content = str_replace("\$table_prefix = 'wp_';", "\$table_prefix = '$db_prefix';", $config_content);
                    
                    // Replace security keys and salts
                    $config_content = preg_replace("/define\(\s*'AUTH_KEY',\s*'put your unique phrase here'\s*\);/", "define( 'AUTH_KEY', '" . shubx_generate_salt() . "' );", $config_content);
                    $config_content = preg_replace("/define\(\s*'SECURE_AUTH_KEY',\s*'put your unique phrase here'\s*\);/", "define( 'SECURE_AUTH_KEY', '" . shubx_generate_salt() . "' );", $config_content);
                    $config_content = preg_replace("/define\(\s*'LOGGED_IN_KEY',\s*'put your unique phrase here'\s*\);/", "define( 'LOGGED_IN_KEY', '" . shubx_generate_salt() . "' );", $config_content);
                    $config_content = preg_replace("/define\(\s*'NONCE_KEY',\s*'put your unique phrase here'\s*\);/", "define( 'NONCE_KEY', '" . shubx_generate_salt() . "' );", $config_content);
                    $config_content = preg_replace("/define\(\s*'AUTH_SALT',\s*'put your unique phrase here'\s*\);/", "define( 'AUTH_SALT', '" . shubx_generate_salt() . "' );", $config_content);
                    $config_content = preg_replace("/define\(\s*'SECURE_AUTH_SALT',\s*'put your unique phrase here'\s*\);/", "define( 'SECURE_AUTH_SALT', '" . shubx_generate_salt() . "' );", $config_content);
                    $config_content = preg_replace("/define\(\s*'LOGGED_IN_SALT',\s*'put your unique phrase here'\s*\);/", "define( 'LOGGED_IN_SALT', '" . shubx_generate_salt() . "' );", $config_content);
                    $config_content = preg_replace("/define\(\s*'NONCE_SALT',\s*'put your unique phrase here'\s*\);/", "define( 'NONCE_SALT', '" . shubx_generate_salt() . "' );", $config_content);
                    
                    // Write the config file
                    $written = @file_put_contents($config_path, $config_content);
                    
                    if (!$written) {
                        $install_error = 'Failed to write wp-config.php. Please verify file write permissions in the root directory.';
                    } else {
                        // 3. Run WordPress Installer
                        try {
                            if (!defined('WP_INSTALLING')) {
                                define('WP_INSTALLING', true);
                            }
                            
                            // Load WordPress core
                            ob_start();
                            require_once $root_dir . '/wp-load.php';
                            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                            ob_end_clean();
                            
                            if (function_exists('wp_install')) {
                                // Install site
                                $result = wp_install($site_title, $admin_user, $admin_email, true, '', $admin_pass);
                                
                                // Activate Society HubX plugin
                                require_once ABSPATH . 'wp-admin/includes/plugin.php';
                                $plugin_path = 'society-hubx/society-hubx.php';
                                if (file_exists(WP_PLUGIN_DIR . '/' . $plugin_path)) {
                                    activate_plugin($plugin_path);
                                }
                                
                                // Enable pretty permalinks (Post name structure)
                                global $wp_rewrite;
                                $wp_rewrite->set_permalink_structure('/%postname%/');
                                $wp_rewrite->flush_rules(true);
                                
                                $install_success = true;
                                
                                // Attempt to self-delete installer script
                                @unlink(__FILE__);
                                
                            } else {
                                $install_error = 'WordPress installer files loaded but installation routine is unavailable.';
                            }
                        } catch (Exception $e) {
                            $install_error = 'An error occurred during installation: ' . $e->getMessage();
                        }
                    }
                }
            }
        }
    }
}

function sanitize_input($val) {
    return htmlspecialchars(trim(strip_tags($val)), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Society HubX — Webapp Setup Wizard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --shubx-primary-grad: linear-gradient(135deg, #8b5cf6 0%, #d946ef 100%);
            --shubx-bg-grad: radial-gradient(circle at 50% -10%, rgba(139, 92, 246, 0.18) 0%, rgba(217, 70, 239, 0.05) 50%, #05050a 100%);
            --bs-body-bg: #05050a;
            --bs-body-color: #f8fafc;
            --bs-card-bg: rgba(17, 20, 39, 0.55);
            --bs-card-border-color: rgba(255, 255, 255, 0.09);
            --bs-border-color: rgba(255, 255, 255, 0.12);
            --bs-form-control-bg: rgba(11, 13, 28, 0.88);
            --bs-form-control-border-color: rgba(255, 255, 255, 0.22);
            --bs-form-control-color: #f8fafc;
            --bs-primary: #d946ef;
        }

        body {
            background: var(--shubx-bg-grad);
            color: var(--bs-body-color);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .shubx-setup-container {
            width: 100%;
            max-width: 800px;
        }

        .shubx-setup-card {
            background: var(--bs-card-bg) !important;
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid var(--bs-card-border-color) !important;
            border-radius: 24px !important;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.4) !important;
            padding: 40px !important;
        }

        .shubx-brand-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .shubx-brand-title {
            background: var(--shubx-primary-grad);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 800;
            letter-spacing: -1px;
            font-size: 2.2rem;
            margin-top: 15px;
        }

        .shubx-setup-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: rgba(217, 70, 239, 0.1);
            border: 1px solid rgba(217, 70, 239, 0.25);
            box-shadow: 0 0 25px rgba(217, 70, 239, 0.15);
            animation: bounce 2s infinite ease-in-out;
        }

        .shubx-input {
            background-color: var(--bs-form-control-bg) !important;
            border: 1px solid var(--bs-form-control-border-color) !important;
            color: var(--bs-form-control-color) !important;
            border-radius: 12px !important;
            padding: 12px 16px !important;
            transition: all 0.25s ease !important;
        }

        .shubx-input:focus {
            background-color: rgba(7, 8, 20, 0.98) !important;
            border-color: #d946ef !important;
            box-shadow: 0 0 0 3px rgba(217, 70, 239, 0.35) !important;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
        }

        .shubx-section-title {
            font-weight: 700;
            font-size: 1.15rem;
            margin-bottom: 20px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--bs-border-color);
            color: #f1f5f9;
        }

        .shubx-btn-primary {
            background: var(--shubx-primary-grad) !important;
            color: #fff !important;
            border: none !important;
            border-radius: 12px !important;
            padding: 14px 28px !important;
            font-weight: 700 !important;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.35) !important;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }

        .shubx-btn-primary:hover {
            transform: translateY(-2px);
            filter: brightness(1.1);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.5) !important;
        }

        .shubx-btn-secondary {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid var(--bs-border-color) !important;
            color: #f1f5f9 !important;
            border-radius: 12px !important;
            padding: 14px 28px !important;
            font-weight: 600 !important;
            transition: all 0.2s ease !important;
        }

        .shubx-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.12) !important;
        }

        .shubx-btn-danger {
            background: rgba(239, 68, 68, 0.15) !important;
            border: 1px solid rgba(239, 68, 68, 0.3) !important;
            color: #f87171 !important;
            border-radius: 12px !important;
            padding: 14px 28px !important;
            font-weight: 600 !important;
            transition: all 0.2s ease !important;
        }

        .shubx-btn-danger:hover {
            background: #ef4444 !important;
            color: #fff !important;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.25) !important;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }
    </style>
</head>
<body>

<div class="shubx-setup-container">
    <div class="card shubx-setup-card border-0">
        
        <!-- Header Section -->
        <div class="shubx-brand-header">
            <div class="shubx-setup-icon">
                <i class="fa-solid fa-building-shield text-primary display-5"></i>
            </div>
            <h1 class="shubx-brand-title">Society HubX</h1>
            <p class="text-muted mb-0">Single-Shot Webapp Setup Wizard</p>
        </div>

        <!-- STATE 1: SUCCESS INSTALL -->
        <?php if ($install_success) : ?>
            <div class="text-center py-4">
                <div class="text-success mb-4">
                    <i class="fa-solid fa-circle-check display-1"></i>
                </div>
                <h3 class="fw-bold">Configuration Complete!</h3>
                <p class="text-muted px-md-5">WordPress has been installed successfully and the Society HubX management engine is activated. To secure your site, this setup file has self-deleted.</p>
                <div class="d-flex justify-content-center gap-3 mt-4">
                    <a href="<?php echo htmlspecialchars(admin_url('admin.php?page=shubx51-setup')); ?>" class="btn shubx-btn-primary">
                        <i class="fa-solid fa-building-circle-check me-2"></i>Configure Society Settings
                    </a>
                    <a href="<?php echo htmlspecialchars(admin_url()); ?>" class="btn shubx-btn-secondary">
                        <i class="fa-solid fa-gauge-high me-2"></i>Open Admin Panel
                    </a>
                </div>
            </div>

        <!-- STATE 2: ALREADY INSTALLED WIZARD -->
        <?php elseif ($is_installed) : ?>
            <div class="text-center py-4">
                <div class="text-primary mb-4">
                    <i class="fa-solid fa-shield-halved display-2"></i>
                </div>
                <h3 class="fw-bold">Webapp Already Installed</h3>
                <p class="text-muted px-md-5 mb-5">WordPress and the Society HubX plugin are already configured on this server. Running this installer again is blocked to prevent data loss.</p>
                
                <div class="card bg-danger bg-opacity-10 border-danger border-opacity-20 p-4 mb-4 rounded-4 text-start">
                    <h5 class="fw-bold text-danger mb-2"><i class="fa-solid fa-triangle-exclamation me-2"></i>Destructive Reset</h5>
                    <p class="small text-danger-emphasis mb-4">If you want to start over from scratch, clicking the button below will drop all database tables, delete the configuration file, and allow you to configure the webapp again. This will delete all resident, flats, and financial logs permanently.</p>
                    <form method="post" action="" onsubmit="return confirm('WARNING: Are you sure you want to delete all database tables and configuration? This action is permanent and cannot be undone!');">
                        <input type="hidden" name="action" value="reset_reinstall" />
                        <button type="submit" class="btn shubx-btn-danger px-4 py-2.5">
                            <i class="fa-solid fa-trash-can me-2"></i>Re-install From Scratch
                        </button>
                    </form>
                </div>

                <div class="d-flex justify-content-center gap-3">
                    <a href="./" class="btn shubx-btn-primary">
                        <i class="fa-solid fa-house me-2"></i>Visit Website
                    </a>
                    <a href="./wp-admin/" class="btn shubx-btn-secondary">
                        <i class="fa-solid fa-gauge me-2"></i>Go to Dashboard
                    </a>
                </div>
            </div>

        <!-- STATE 3: FORM CONFIGURATION -->
        <?php else : ?>
            
            <?php if (!empty($install_error)) : ?>
                <div class="alert alert-danger border-0 py-3 px-4 rounded-3 mb-4 d-flex align-items-start gap-3">
                    <i class="fa-solid fa-triangle-exclamation mt-1"></i>
                    <div>
                        <strong class="d-block mb-1">Configuration Error</strong>
                        <span><?php echo $install_error; ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="action" value="run_install" />

                <!-- Section 1: DB Credentials -->
                <div class="shubx-section-title">
                    <i class="fa-solid fa-database text-primary me-2"></i>1. Database Configuration
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="db_host" class="form-label">Database Host</label>
                        <input type="text" name="db_host" id="db_host" class="form-control shubx-input" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required />
                    </div>
                    <div class="col-md-6">
                        <label for="db_name" class="form-label">Database Name</label>
                        <input type="text" name="db_name" id="db_name" class="form-control shubx-input" placeholder="e.g. societydb" value="<?php echo htmlspecialchars($_POST['db_name'] ?? ''); ?>" required />
                    </div>
                    <div class="col-md-6">
                        <label for="db_user" class="form-label">Database Username</label>
                        <input type="text" name="db_user" id="db_user" class="form-control shubx-input" placeholder="e.g. root" value="<?php echo htmlspecialchars($_POST['db_user'] ?? ''); ?>" required />
                    </div>
                    <div class="col-md-6">
                        <label for="db_pass" class="form-label">Database Password</label>
                        <input type="password" name="db_pass" id="db_pass" class="form-control shubx-input" placeholder="••••••••" />
                    </div>
                    <div class="col-md-6">
                        <label for="db_prefix" class="form-label">Table Prefix</label>
                        <input type="text" name="db_prefix" id="db_prefix" class="form-control shubx-input" value="<?php echo htmlspecialchars($_POST['db_prefix'] ?? 'wp_'); ?>" required />
                    </div>
                </div>

                <!-- Section 2: Admin Site Setup -->
                <div class="shubx-section-title mt-4">
                    <i class="fa-solid fa-user-gear text-primary me-2"></i>2. Administrator & Site Setup
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-12">
                        <label for="site_title" class="form-label">Site Title</label>
                        <input type="text" name="site_title" id="site_title" class="form-control shubx-input" value="<?php echo htmlspecialchars($_POST['site_title'] ?? 'Society HubX Community'); ?>" required />
                    </div>
                    <div class="col-md-6">
                        <label for="admin_user" class="form-label">Admin Username</label>
                        <input type="text" name="admin_user" id="admin_user" class="form-control shubx-input" value="<?php echo htmlspecialchars($_POST['admin_user'] ?? 'admin'); ?>" required />
                    </div>
                    <div class="col-md-6">
                        <label for="admin_pass" class="form-label">Admin Password</label>
                        <input type="password" name="admin_pass" id="admin_pass" class="form-control shubx-input" placeholder="Choose a strong password" required />
                    </div>
                    <div class="col-md-12">
                        <label for="admin_email" class="form-label">Admin Email Address</label>
                        <input type="email" name="admin_email" id="admin_email" class="form-control shubx-input" placeholder="admin@yoursite.com" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>" required />
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn shubx-btn-primary btn-lg fs-5 py-3">
                        <i class="fa-solid fa-rocket me-2"></i>Install & Run Setup
                    </button>
                </div>
            </form>

        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
