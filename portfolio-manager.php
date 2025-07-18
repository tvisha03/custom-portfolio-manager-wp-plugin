<?php
/**
 * Plugin Name: Portfolio Manager
 * Description: A custom WordPress plugin to manage and display portfolio projects.
 * Version: 1.0.0
 * Author: Tvisha
 * Author URI: yourwebsite.com (optional, replace with your website if you have one)
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: portfolio-manager
 */

// Exit if accessed directly to prevent direct access to the file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Function to create the custom database table for portfolio projects.
 * This function is hooked to the plugin activation.
 */
function pm_create_portfolio_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_projects';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        description text NOT NULL,
        image_url varchar(255) DEFAULT '' NOT NULL,
        technologies varchar(255) DEFAULT '' NOT NULL,
        live_url varchar(255) DEFAULT '' NOT NULL,    /* New column for live project URL */
        github_url varchar(255) DEFAULT '' NOT NULL,  /* New column for GitHub repository URL */
        status varchar(20) DEFAULT 'published' NOT NULL, /* New column for project status */
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
// Hook our table creation function to the plugin activation event.
register_activation_hook( __FILE__, 'pm_create_portfolio_table' );


/**
 * Add a new top-level menu page to the WordPress admin dashboard.
 */
function pm_add_admin_menu() {
    add_menu_page(
        'Portfolio Manager Settings', // The title that appears in the browser tab for this page
        'Portfolio Manager',          // The text that appears in the admin menu sidebar
        'manage_options',             // The capability required to access this menu (manage_options is typically for administrators)
        'portfolio-manager',          // A unique slug for this menu page (used in the URL)
        'pm_admin_page_content',      // The name of the function that will output the content for this page
        'dashicons-portfolio',        // Icon URL (using a built-in WordPress Dashicon)
        20                            // Position in the menu (20 is typically below "Pages")
    );
}
// Hook our menu creation function to the 'admin_menu' action.
add_action( 'admin_menu', 'pm_add_admin_menu' );


/**
 * Function to output the content for our Portfolio Manager admin page.
 * This now handles adding, editing, and listing projects, including live/github URLs.
 */
function pm_admin_page_content() {
    global $wpdb; // Access the global WordPress database object
    $table_name = $wpdb->prefix . 'my_projects'; // Define our custom table name

    // Initialize variables for edit mode
    $current_project = null;
    $form_title = 'Add New Project';
    $submit_button_text = 'Add Project';
    $action_nonce = 'pm_add_project_action';
    $nonce_name = 'pm_add_project_nonce';

    // --- Handle Edit Action (PHP Logic) ---
    // Check if an 'edit' action is requested via URL and a valid project ID is provided
    if ( isset( $_GET['action'] ) && $_GET['action'] == 'edit' && isset( $_GET['id'] ) ) {
        $project_id = intval( $_GET['id'] ); // Sanitize ID to an integer

        // Fetch the project data from the database
        $current_project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $project_id ) );

        // If project not found or ID is invalid, show error and revert to add mode
        if ( ! $current_project ) {
            echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> Project not found or invalid ID.</p></div>';
            $current_project = null; // Revert to add mode
        } else {
            // If project found, switch form to 'Edit' mode
            $form_title = 'Edit Project';
            $submit_button_text = 'Update Project';
            $action_nonce = 'pm_edit_project_action'; // Different nonce action for edit for clarity/security
            $nonce_name = 'pm_edit_project_nonce';
        }
    }

    // --- Handle Delete Action (PHP Logic) ---
    // Check if a 'delete' action is requested via URL and a valid project ID is provided
    if ( isset( $_GET['action'] ) && $_GET['action'] == 'delete' && isset( $_GET['id'] ) ) {
        $project_id = intval( $_GET['id'] ); // Sanitize ID to an integer

        // IMPORTANT: Verify nonce for security before deleting!
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'pm_delete_project_action_' . $project_id ) ) {
            echo '<div class="notice notice-error is-dismissible"><p>Security check failed. Please try again.</p></div>';
        } else {
            // Perform the deletion from the database
            $deleted_rows = $wpdb->delete(
                $table_name, // Table name
                array( 'id' => $project_id ), // WHERE clause
                array( '%d' ) // Format for the WHERE clause value
            );

            if ( $deleted_rows ) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> Project deleted successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> Could not delete project or project not found. ' . $wpdb->last_error . '</p></div>';
            }
        }
    }


    // --- Handle Form Submission (PHP Logic for adding/updating projects) ---
    if ( isset( $_POST['submit_portfolio_project'] ) ) {
        // Check nonce for security based on whether it's an add or edit operation
        $expected_action = isset( $_POST['project_id'] ) ? 'pm_edit_project_action' : 'pm_add_project_action';
        $expected_nonce_name = isset( $_POST['project_id'] ) ? 'pm_edit_project_nonce' : 'pm_add_project_nonce';

        if ( ! isset( $_POST[ $expected_nonce_name ] ) || ! wp_verify_nonce( $_POST[ $expected_nonce_name ], $expected_action ) ) {
            echo '<div class="notice notice-error is-dismissible"><p>Security check failed. Please try again.</p></div>';
        } else {
            // Sanitize and retrieve form data
            $title       = sanitize_text_field( $_POST['project_title'] );
            $description = sanitize_textarea_field( $_POST['project_description'] );
            $image_url   = esc_url_raw( $_POST['project_image_url'] );
            $technologies = sanitize_text_field( $_POST['project_technologies'] );
            $live_url     = esc_url_raw( $_POST['project_live_url'] ); // NEW FIELD
            $github_url   = esc_url_raw( $_POST['project_github_url'] ); // NEW FIELD
            $status       = sanitize_text_field( $_POST['project_status'] ); // NEW FIELD
            $project_id_from_post = isset( $_POST['project_id'] ) ? intval( $_POST['project_id'] ) : 0;

            // Basic validation
            if ( empty( $title ) || empty( $description ) ) {
                echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> Project Title and Description cannot be empty.</p></div>';
            } else {
                $data = array(
                    'title'       => $title,
                    'description' => $description,
                    'image_url'   => $image_url,
                    'technologies' => $technologies,
                    'live_url'    => $live_url,    // NEW FIELD
                    'github_url'  => $github_url,  // NEW FIELD
                    'status'      => $status,
                );
                $format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ); // Add one more '%s' for status

                if ( $project_id_from_post > 0 && $expected_action == 'pm_edit_project_action' ) {
                    // UPDATE existing project
                    $where = array( 'id' => $project_id_from_post );
                    $where_format = array( '%d' );
                    $result = $wpdb->update( $table_name, $data, $where, $format, $where_format );

                    if ( $result !== false ) {
                        echo '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> Project updated successfully.</p></div>';
                        $current_project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $project_id_from_post ) );
                    } else {
                        echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> Could not update project. ' . $wpdb->last_error . '</p></div>';
                    }
                } else {
                    // INSERT new project
                    $result = $wpdb->insert( $table_name, $data, $format );

                    if ( $result ) {
                        echo '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> Project added successfully.</p></div>';
                        $current_project = null;
                    } else {
                        echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> Could not add project. ' . $wpdb->last_error . '</p></div>';
                    }
                }
            }
        }
    }


    // 3. Display the Add/Edit Project Form (HTML)
    ?>
    <div class="wrap">
        <h1>Portfolio Manager</h1>

        <?php if ( $current_project ) : // Show "Back to Add New" button if in edit mode ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=portfolio-manager' ) ); ?>" class="button button-secondary">← Back to Add New Project</a>
            <br><br>
        <?php endif; ?>

        <h2><?php echo esc_html( $form_title ); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field( $action_nonce, $nonce_name ); ?>
            <?php if ( $current_project ) : // Hidden input for project ID if editing ?>
                <input type="hidden" name="project_id" value="<?php echo esc_attr( $current_project->id ); ?>">
            <?php endif; ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="project_title">Project Title</label></th>
                        <td><input type="text" name="project_title" id="project_title" class="regular-text" value="<?php echo esc_attr( $current_project ? $current_project->title : '' ); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="project_description">Project Description</label></th>
                        <td><textarea name="project_description" id="project_description" rows="5" cols="50" class="large-text" required><?php echo esc_textarea( $current_project ? $current_project->description : '' ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="project_image_url">Image URL</label></th>
                        <td><input type="url" name="project_image_url" id="project_image_url" class="regular-text" placeholder="e.g., https://example.com/image.jpg" value="<?php echo esc_attr( $current_project ? $current_project->image_url : '' ); ?>">
                        <p class="description">Provide a direct URL to the project's main image.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="project_technologies">Technologies Used</label></th>
                        <td><input type="text" name="project_technologies" id="project_technologies" class="regular-text" placeholder="e.g., HTML, CSS, JS, PHP, MySQL" value="<?php echo esc_attr( $current_project ? $current_project->technologies : '' ); ?>">
                        <p class="description">Comma-separated list (e.g., React, Node.js, MongoDB)</p></td>
                    </tr>
                    <tr> <th scope="row"><label for="project_live_url">Live Project URL</label></th>
                        <td><input type="url" name="project_live_url" id="project_live_url" class="regular-text" placeholder="e.g., https://live-demo.com" value="<?php echo esc_attr( $current_project ? $current_project->live_url : '' ); ?>">
                        <p class="description">Link to the live demo or deployed version of the project.</p></td>
                    </tr>
                    <tr> <th scope="row"><label for="project_github_url">GitHub URL</label></th>
                        <td><input type="url" name="project_github_url" id="project_github_url" class="regular-text" placeholder="e.g., https://github.com/your-repo" value="<?php echo esc_attr( $current_project ? $current_project->github_url : '' ); ?>">
                        <p class="description">Link to the GitHub repository for the project.</p></td>
                    </tr>
                    <tr> <th scope="row"><label for="project_status">Project Status</label></th>
                        <td>
                            <select name="project_status" id="project_status">
                                <option value="published" <?php selected( $current_project ? $current_project->status : 'published', 'published' ); ?>>Published</option>
                                <option value="draft" <?php selected( $current_project ? $current_project->status : '', 'draft' ); ?>>Draft</option>
                                <option value="archived" <?php selected( $current_project ? $current_project->status : '', 'archived' ); ?>>Archived</option>
                           </select>
                           <p class="description">Only 'Published' projects will show on the frontend.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button( $submit_button_text, 'primary', 'submit_portfolio_project' ); ?>
        </form>

        <hr class="wp-header-end">

        <h2>Existing Projects</h2>
        <?php
        // 4. Query and Display Existing Projects (PHP & HTML)
        $projects = $wpdb->get_results( "SELECT id, title, technologies FROM $table_name ORDER BY id DESC" );

        if ( $projects ) {
            ?>
            <table class="wp-list-table widefat fixed striped posts">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Title</th>
                        <th scope="col">Technologies Used</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $projects as $project ) : ?>
                        <tr>
                            <td><?php echo esc_html( $project->id ); ?></td>
                            <td><?php echo esc_html( $project->title ); ?></td>
                            <td><?php echo esc_html( $project->technologies ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=portfolio-manager&action=edit&id=' . $project->id ) ); ?>">Edit</a> |
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=portfolio-manager&action=delete&id=' . $project->id ), 'pm_delete_project_action_' . $project->id ) ); ?>">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        } else {
            echo '<p>No projects found. Add a new project using the form above!</p>';
        }
        ?>
    </div>
    <?php
}


/**
 * Shortcode to display portfolio projects on the frontend.
 * Usage: [my_projects]
 */
function pm_display_projects_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_projects';

    $projects = $wpdb->get_results( "SELECT * FROM $table_name WHERE status = 'published' ORDER BY id DESC" );
    
    ob_start();

if ( $projects ) {
    ?>
    <div class="pm-portfolio-container">
        <div class="pm-sort-controls">
            <label for="pm-sort-by">Sort by:</label>
            <select id="pm-sort-by">
                <option value="latest">Newest First</option>
                <option value="oldest">Oldest First</option>
                <option value="title_asc">Title (A-Z)</option>
                <option value="title_desc">Title (Z-A)</option>
            </select>
        </div>
        <button id="pm-dark-mode-toggle" class="pm-button pm-button-toggle" aria-label="Toggle dark mode">
            <i class="fas fa-sun pm-icon-light"></i>
            <i class="fas fa-moon pm-icon-dark"></i>
        </button>
        <div class="pm-portfolio-grid">
            <?php foreach ( $projects as $project ) : ?>
                <div class="pm-portfolio-item" data-project-id="<?php echo esc_attr( $project->id ); ?>">
                    <?php if ( ! empty( $project->image_url ) ) : ?>
                        <div class="pm-portfolio-image">
                            <img src="<?php echo esc_url( $project->image_url ); ?>" alt="<?php echo esc_attr( $project->title ); ?>">
                        </div>
                    <?php endif; ?>
                    <div class="pm-portfolio-content">
                        <h3><?php echo esc_html( $project->title ); ?></h3>
                        <p class="pm-portfolio-description"><?php echo esc_html( $project->description ); ?></p>
                        <?php if ( ! empty( $project->technologies ) ) : ?>
                            <div class="pm-portfolio-tech-chips">
                                <strong>Technologies:</strong>
                                <?php
                                $technologies_array = array_map( 'trim', explode( ',', $project->technologies ) );
                                foreach ( $technologies_array as $tech_tag ) {
                                    if ( ! empty( $tech_tag ) ) {
                                        echo '<span class="pm-tag-chip">' . esc_html( $tech_tag ) . '</span>';
                                    }
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                        <div class="pm-project-links">
                            <?php if ( ! empty( $project->live_url ) ) : ?>
                                <a href="<?php echo esc_url( $project->live_url ); ?>" target="_blank" class="button pm-button pm-button-live">View Live</a>
                            <?php endif; ?>
                            <?php if ( ! empty( $project->github_url ) ) : ?>
                                <a href="<?php echo esc_url( $project->github_url ); ?>" target="_blank" class="button pm-button pm-button-github">GitHub</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
} else {
    echo '<p>No portfolio projects to display yet. Please add some from the admin dashboard!</p>';
}
?>

<div id="pm-project-modal" class="pm-modal">
    <div class="pm-modal-content">
        <span class="pm-modal-close">&times;</span>
        <h2 id="pm-modal-title"></h2>
        <div class="pm-modal-image-container">
            <img id="pm-modal-image" src="" alt="">
        </div>
        <p id="pm-modal-description"></p>
        <p id="pm-modal-technologies"></p>
        <div class="pm-modal-links">
            <a href="#" id="pm-modal-live-link" target="_blank" class="button pm-button pm-button-live" style="display: none;">View Live</a>
            <a href="#" id="pm-modal-github-link" target="_blank" class="button pm-button pm-button-github" style="display: none;">GitHub</a>
        </div>
        </div>
</div>
<?php
return ob_get_clean();
}
add_shortcode( 'my_projects', 'pm_display_projects_shortcode' );
/**
 * Enqueue custom styles for both admin and frontend.
 */
function pm_enqueue_styles() {
    if ( is_admin() ) {
        wp_enqueue_style( 'pm-admin-styles', plugins_url( 'style.css', __FILE__ ), array(), filemtime( plugin_dir_path( __FILE__ ) . 'style.css' ), 'all' );
    }

    if ( ! is_admin() ) {
        wp_enqueue_style( 'pm-frontend-styles', plugins_url( 'style.css', __FILE__ ), array(), filemtime( plugin_dir_path( __FILE__ ) . 'style.css' ), 'all' );
        // NEW: Enqueue Font Awesome for icons
        wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css', array(), '6.0.0', 'all' );
        // Enqueue JavaScript for frontend
        wp_enqueue_script( 'pm-frontend-script', plugins_url( 'script.js', __FILE__ ), array(), '1.0.0', true );
        
        // Prepare project data for JavaScript
        global $wpdb;
        $table_name = $wpdb->prefix . 'my_projects';
        $projects = $wpdb->get_results( "SELECT * FROM $table_name WHERE status = 'published' ORDER BY id DESC" );
        $projects_for_js = array();
        
        foreach ($projects as $project) {
            $project_data = (array) $project; // Convert object to array
            // Add HTML-formatted technologies for the modal
            $technologies_array = array_map( 'trim', explode( ',', $project_data['technologies'] ) );
            $tech_chips_html = '';
            foreach ( $technologies_array as $tech_tag ) {
                if ( ! empty( $tech_tag ) ) {
                    $tech_chips_html .= '<span class="pm-tag-chip">' . esc_html( $tech_tag ) . '</span>';
                }
            }
            $project_data['technologies_html'] = $tech_chips_html;
            $projects_for_js[] = $project_data;
        }
        
        // Pass the prepared data to JavaScript
        wp_localize_script( 'pm-frontend-script', 'pmProjectsData', $projects_for_js );
    }
}
add_action( 'admin_enqueue_scripts', 'pm_enqueue_styles' );
add_action( 'wp_enqueue_scripts', 'pm_enqueue_styles' );