<?php
/*
Plugin Name: Export Posts to WooCommerce Products
Description: Export WordPress posts as WooCommerce products with the same name, featured image, gallery images, and no price.
Version: 1.0
Author: Your Name
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Add admin menu
add_action( 'admin_menu', 'epp_add_admin_menu' );
function epp_add_admin_menu() {
    add_menu_page(
        'Export Posts to Products',
        'Export Posts',
        'manage_options',
        'export-posts-to-products',
        'epp_admin_page',
        'dashicons-products',
        56
    );
}

// Admin page content
function epp_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_POST['epp_export'] ) && check_admin_referer( 'epp_export_nonce' ) ) {
        $count = epp_export_posts_to_products();
        echo '<div class="updated"><p>Exported ' . esc_html( $count ) . ' posts as products.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Export Posts to WooCommerce Products</h1>
        <form method="post">
            <?php wp_nonce_field( 'epp_export_nonce' ); ?>
            <p>
                <input type="submit" name="epp_export" class="button button-primary" value="Export Posts" />
            </p>
        </form>
    </div>
    <?php
}

// Export function
function epp_export_posts_to_products() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return 0;
    }

    $args = array(
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    );

    $posts = get_posts( $args );
    $count = 0;

    foreach ( $posts as $post ) {
        // Check if product with same title exists
        $existing = get_page_by_title( $post->post_title, OBJECT, 'product' );
        if ( $existing ) {
            continue; // Skip if product exists
        }

        // Create product post
        $product_id = wp_insert_post( array(
            'post_title'  => $post->post_title,
            'post_content'=> $post->post_content,
            'post_status' => 'publish',
            'post_type'   => 'product',
        ) );

        if ( is_wp_error( $product_id ) || ! $product_id ) {
            continue;
        }

        // Set product type to simple
        wp_set_object_terms( $product_id, 'simple', 'product_type' );

        // Copy featured image
        $thumbnail_id = get_post_thumbnail_id( $post->ID );
        if ( $thumbnail_id ) {
            set_post_thumbnail( $product_id, $thumbnail_id );
        }

        // Copy gallery images
        $gallery_ids = get_post_meta( $post->ID, '_product_image_gallery', true );
        if ( ! $gallery_ids ) {
            // Try to get gallery from post meta or other source if needed
            $gallery_ids = '';
        }
        if ( $gallery_ids ) {
            update_post_meta( $product_id, '_product_image_gallery', $gallery_ids );
        }

        // Do not set price (leave empty)
        update_post_meta( $product_id, '_regular_price', '' );
        update_post_meta( $product_id, '_price', '' );

        $count++;
    }

    return $count;
}
?>
