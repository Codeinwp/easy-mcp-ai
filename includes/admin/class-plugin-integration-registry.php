<?php
namespace Easy_MCP_AI\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}








class Plugin_Integration_Registry {

    public static function get_groups(): array {
        return array(
            array(
                'slug'        => 'woocommerce',
                'name'        => 'WooCommerce',
                'description' => 'Manage products, orders, customers, coupons, reports, webhooks, and store settings via the WooCommerce REST API v3.',
                'status'      => 'stable',
                'requires'    => 'WooCommerce plugin must be active.',
                'detector'    => 'class:WooCommerce',
                'tools'       => array(
                    array( 'name' => 'wp_wc_list_products',           'description' => 'List products with filtering by status, category, price range, and stock level.',           'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_get_product',             'description' => 'Get a single product with all fields including variations and attributes.',                   'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_create_product',          'description' => 'Create a new product (simple, variable, grouped, or external).',                             'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_update_product',          'description' => 'Update an existing product\'s details, price, stock, or status.',                           'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_delete_product',          'description' => 'Delete a product permanently.',                                                               'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_list_product_variations', 'description' => 'List all variations for a variable product.',                                                 'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_create_product_variation', 'description' => 'Create a new variation for a variable product.',                                                 'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_get_product_variation',    'description' => 'Get a single variation for a variable product.',                                              'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_update_product_variation', 'description' => 'Update an existing product variation.',                                                       'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_delete_product_variation', 'description' => 'Delete a product variation permanently.',                                                     'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_list_product_attributes',  'description' => 'List all global product attributes.',                                                         'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_create_product_attribute', 'description' => 'Create a new global product attribute.',                                                      'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_set_product_attributes',   'description' => 'Set the attributes assigned to a specific product.',                                          'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_batch_update_products',    'description' => 'Create, update, or delete multiple products in a single batch request.',                      'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_batch_update_variations',  'description' => 'Create, update, or delete multiple product variations in a single batch request.',            'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_batch_update_orders',      'description' => 'Create, update, or delete multiple orders in a single batch request.',                        'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_list_product_categories', 'description' => 'List all product categories with IDs and slugs.',                                            'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_list_orders',             'description' => 'List orders with filtering by status, customer, and date range.',                            'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_get_order',               'description' => 'Get a single order including line items, billing, and shipping details.',                     'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_create_order',            'description' => 'Create a new order programmatically.',                                                        'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_update_order',            'description' => 'Update an order\'s status, notes, or address.',                                             'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_list_order_notes',        'description' => 'List all notes attached to an order.',                                                        'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_create_order_note',       'description' => 'Add a note (customer-visible or private) to an order.',                                      'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_list_order_refunds',      'description' => 'List refunds issued for an order.',                                                           'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_list_customers',          'description' => 'List customers with filtering by email, role, and date.',                                     'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_get_customer',            'description' => 'Get a single customer with billing, shipping, and order stats.',                              'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_create_customer',         'description' => 'Create a new WooCommerce customer account.',                                                  'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_update_customer',         'description' => 'Update a customer\'s profile, billing, or shipping address.',                               'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_delete_customer',         'description' => 'Delete a WooCommerce customer account.',                                                      'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_list_coupons',            'description' => 'List all coupons with their discount type and usage stats.',                                  'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_get_coupon',              'description' => 'Get a single coupon by ID: code, amount, discount type, expiry, usage limits and restrictions.', 'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_create_coupon',           'description' => 'Create a coupon with discount type, amount, expiry, and usage limits.',                      'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_update_coupon',           'description' => 'Update an existing coupon.',                                                                  'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_delete_coupon',           'description' => 'Delete a coupon.',                                                                            'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_report_sales',            'description' => 'Get sales report for a date range: revenue, orders, items, and refunds.',                    'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_report_top_sellers',      'description' => 'Get top-selling products ranked by quantity sold.',                                           'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_report_orders',           'description' => 'Get order count broken down by status.',                                                      'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_report_products',         'description' => 'Get product count broken down by type.',                                                      'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_report_customers',        'description' => 'Get customer count broken down by type.',                                                     'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_list_webhooks',           'description' => 'List all configured WooCommerce webhooks.',                                                   'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_create_webhook',          'description' => 'Create a webhook that fires on a WooCommerce event topic (e.g. order.created, product.updated).', 'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_update_webhook',          'description' => 'Update a webhook\'s delivery URL, topic, or status.',                                       'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_delete_webhook',          'description' => 'Delete a webhook.',                                                                           'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_list_shipping_zones',     'description' => 'List all shipping zones.',                                                                    'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_list_shipping_methods',   'description' => 'List shipping methods configured in a shipping zone.',                                        'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_list_tax_rates',          'description' => 'List all tax rates with country, state, and tax class.',                                      'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_wc_list_payment_gateways',   'description' => 'List all payment gateways and their enabled/disabled status.',                               'type' => 'read',  'api' => 'plugin_rest' ),
                ),
            ),
            array(
                'slug'        => 'acf',
                'name'        => 'ACF / Secure Custom Fields',
                'description' => 'Read and write ACF field values on posts, pages, users, and terms via the native WordPress REST API. Fields appear under the "acf" key in REST responses.',
                'status'      => 'stable',
                'requires'    => 'ACF or Secure Custom Fields must be active. Field groups must have "Show in REST API" enabled.',
                'detector'    => 'class:ACF',
                'tools'       => array(
                    array( 'name' => 'wp_acf_get_fields',         'description' => 'Get all ACF field values for a post or page. Returns data under the acf key.',           'type' => 'read',  'api' => 'wp_rest' ),
                    array( 'name' => 'wp_acf_update_fields',      'description' => 'Update one or more ACF field values on a post or page.',                                  'type' => 'write', 'api' => 'wp_rest' ),
                    array( 'name' => 'wp_acf_get_user_fields',    'description' => 'Get ACF field values attached to a WordPress user.',                                      'type' => 'read',  'api' => 'wp_rest' ),
                    array( 'name' => 'wp_acf_update_user_fields', 'description' => 'Update ACF field values on a WordPress user.',                                            'type' => 'write', 'api' => 'wp_rest' ),
                    array( 'name' => 'wp_acf_get_term_fields',    'description' => 'Get ACF field values attached to a taxonomy term.',                                       'type' => 'read',  'api' => 'wp_rest' ),
                    array( 'name' => 'wp_acf_list_field_groups',  'description' => 'List all registered ACF field groups with keys, labels, and field definitions.',          'type' => 'read',  'api' => 'php' ),
                ),
            ),
            array(
                'slug'        => 'the-events-calendar',
                'name'        => 'The Events Calendar',
                'description' => 'Create and manage events, venues, and organizers via The Events Calendar REST API.',
                'status'      => 'stable',
                'requires'    => 'The Events Calendar (free) must be active.',
                'detector'    => 'class:Tribe__Events__Main',
                'tools'       => array(
                    array( 'name' => 'wp_tec_list_events',      'description' => 'List events with filtering by date range, venue, organizer, category, and search.',  'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_tec_get_event',        'description' => 'Get a single event with full details including venue and organizer info.',            'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_tec_create_event',     'description' => 'Create a new event. Required: title, start_date, end_date (YYYY-MM-DD HH:MM:SS).',   'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_tec_update_event',     'description' => 'Update an existing event\'s details, dates, or venue.',                              'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_tec_delete_event',     'description' => 'Delete an event permanently.',                                                        'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_tec_list_venues',      'description' => 'List all venues with name, address, and coordinates.',                                'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_tec_get_venue',        'description' => 'Get a single venue.',                                                                 'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_tec_list_organizers',  'description' => 'List all organizers with contact details.',                                           'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_tec_create_venue',     'description' => 'Create a new venue.',                                                                 'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_tec_create_organizer', 'description' => 'Create a new organizer.',                                                             'type' => 'write', 'api' => 'plugin_rest' ),
                ),
            ),
            array(
                'slug'        => 'redirection',
                'name'        => 'Redirection',
                'description' => 'Create, update and delete redirects, and read the 404 log, through the Redirection plugin\'s REST API.',
                'status'      => 'stable',
                'requires'    => 'Redirection must be active and its setup (Tools › Redirection) finished.',
                'detector'    => 'class:Red_Item',
                'tools'       => array(
                    array( 'name' => 'wp_redirection_list_redirects',   'description' => 'List redirects, filtered by source, target, group, status or action.',                    'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_redirection_get_redirect',     'description' => 'Get one redirect by id.',                                                                 'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_redirection_create_redirect',  'description' => 'Create a redirect from a path to a target, or an HTTP error for a path.',                'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_redirection_update_redirect',  'description' => 'Change a redirect\'s fields or switch it on or off; unsent fields keep their values.',   'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_redirection_delete_redirects', 'description' => 'Permanently delete redirects by id.',                                                     'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_redirection_list_groups',      'description' => 'List redirect groups with their ids and redirect counts.',                                'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_redirection_list_404s',        'description' => 'Read the 404 log, or the most frequent 404 URLs.',                                        'type' => 'read',  'api' => 'plugin_rest' ),
                ),
            ),
            array(
                'slug'        => 'buddypress',
                'name'        => 'BuddyPress',
                'description' => 'Manage community members, activity streams, groups, and private messages via the BuddyPress REST API.',
                'status'      => 'stable',
                'requires'    => 'BuddyPress (free) must be active. Activity, Groups, and Messages components must be enabled in BuddyPress settings for their respective tools.',
                'detector'    => 'class:BuddyPress',
                'tools'       => array(
                    array( 'name' => 'wp_bp_list_members',         'description' => 'List BuddyPress members with filtering by type and search.',                                            'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_bp_get_member',           'description' => 'Get a member\'s BuddyPress profile including extended profile (xprofile) fields.',                    'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_bp_list_activity',        'description' => 'List the activity stream. Requires Activity component enabled in BuddyPress settings.',                'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_bp_create_activity',      'description' => 'Post a new activity item to the stream. Requires Activity component.',                                  'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_bp_delete_activity',      'description' => 'Delete an activity item. Requires Activity component.',                                                 'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_bp_list_groups',          'description' => 'List BuddyPress groups. Requires Groups component enabled.',                                            'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_bp_get_group',            'description' => 'Get a single BuddyPress group with description and member count. Requires Groups component.',          'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_bp_list_group_members',   'description' => 'List members of a BuddyPress group. Requires Groups component.',                                       'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_bp_list_message_threads', 'description' => 'List private message threads. Requires Messages component enabled in BuddyPress settings.',            'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_bp_get_message_thread',   'description' => 'Get a single message thread with all replies. Requires Messages component.',                           'type' => 'read',  'api' => 'plugin_rest' ),
                ),
            ),
            array(
                'slug'        => 'yoast-seo',
                'name'        => 'Yoast SEO',
                'description' => 'Read rendered SEO head tags for any URL and read/update SEO meta (title, description, focus keyword, OG/Twitter) on posts, pages, categories, tags and other terms.',
                'status'      => 'stable',
                'requires'    => 'Yoast SEO (free or premium) must be active.',
                'detector'    => 'class:WPSEO_Options',
                'tools'       => array(
                    array( 'name' => 'wp_yoast_get_head',        'description' => 'Get the full rendered SEO head (title, meta, OG, Twitter, schema JSON-LD) for any URL. Useful for auditing or headless CMS rendering.',                               'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_yoast_get_post_seo',    'description' => 'Get structured Yoast SEO metadata for a post or page: title, description, robots, canonical, OG, Twitter, and schema data.',                                          'type' => 'read',  'api' => 'wp_rest' ),
                    array( 'name' => 'wp_yoast_update_post_seo', 'description' => 'Update Yoast SEO fields on a post: seo_title, meta_description, focus_keyword, is_cornerstone, og_title, og_description, twitter_title, twitter_description.',        'type' => 'write', 'api' => 'php' ),
                    array( 'name' => 'wp_yoast_get_term_seo',    'description' => 'Get the Yoast SEO values of a category, tag or other term: title, description, focus keyphrase, cornerstone, noindex, canonical, breadcrumb title, OG and X fields.', 'type' => 'read',  'api' => 'php' ),
                    array( 'name' => 'wp_yoast_update_term_seo', 'description' => 'Update Yoast SEO values on a category, tag or other term, as Yoast\'s own term editor does: seo_title, meta_description, focus_keyword, noindex, canonical, OG and X fields.', 'type' => 'write', 'api' => 'php' ),
                ),
            ),
            array(
                'slug'        => 'rank-math',
                'name'        => 'Rank Math SEO',
                'description' => 'Read rendered SEO head tags for any URL and read/update SEO meta on posts and pages via Rank Math.',
                'status'      => 'stable',
                'requires'    => 'Rank Math SEO (free or pro) must be active. For wp_rm_get_head, enable Headless CMS Support in Rank Math → General Settings → Others.',
                'detector'    => 'function:rank_math',
                'tools'       => array(
                    array( 'name' => 'wp_rm_get_head',        'description' => 'Get rendered SEO head HTML for any URL. Requires Headless CMS Support enabled in Rank Math → General Settings → Others → Headless CMS Support.',        'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_rm_get_post_seo',    'description' => 'Get all Rank Math SEO meta fields for a post: title, description, focus keyword, robots, canonical, OG fields, Twitter fields.',                        'type' => 'read',  'api' => 'php' ),
                    array( 'name' => 'wp_rm_update_post_seo', 'description' => 'Update Rank Math SEO fields on a post: rank_math_title, rank_math_description, rank_math_focus_keyword, rank_math_canonical_url, OG and Twitter fields.', 'type' => 'write', 'api' => 'php' ),
                ),
            ),
            array(
                'slug'        => 'aioseo',
                'name'        => 'All in One SEO (AIOSEO)',
                'description' => 'Read and update AIOSEO SEO metadata on posts and pages via the WordPress REST API using the aioseo_meta_data field.',
                'status'      => 'stable',
                'requires'    => 'AIOSEO 4.9.8+ — REST API is free in core; pre-4.9.8 free needs the legacy REST addon.',
                'detector'    => 'function:aioseo',
                'tools'       => array(
                    array( 'name' => 'wp_aioseo_get_post_seo',    'description' => 'Get AIOSEO SEO data for a post: title, description, OG fields, Twitter fields, no_index, canonical_url.',                                                        'type' => 'read',  'api' => 'wp_rest' ),
                    array( 'name' => 'wp_aioseo_update_post_seo', 'description' => 'Update AIOSEO SEO metadata on a post.',                                                                                                                            'type' => 'write', 'api' => 'wp_rest' ),
                    array( 'name' => 'wp_aioseo_get_breadcrumb',  'description' => 'Get the AIOSEO breadcrumb trail (links and labels) for a post or page.',                                                                                            'type' => 'read',  'api' => 'wp_rest' ),
                ),
            ),
            array(
                'slug'        => 'seopress',
                'name'        => 'SEOPress',
                'description' => 'Read and update SEOPress SEO meta (title, description, robots, canonical, OG/Twitter, target keywords) and content analysis via the official seopress/v1 REST API.',
                'status'      => 'stable',
                'requires'    => 'SEOPress (free or PRO) must be active.',
                'detector'    => 'function:seopress_get_service',
                'tools'       => array(
                    array( 'name' => 'wp_seopress_get_post_seo',           'description' => 'Get SEOPress SEO meta for a post: title, description, robots, canonical, OG/Twitter fields, and target keywords.',         'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_seopress_update_post_title_desc', 'description' => 'Update the SEOPress meta title and meta description on a post.',                                                          'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_seopress_update_post_robots',     'description' => 'Update SEOPress robots directives on a post: noindex, nofollow, and other meta robots settings.',                          'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_seopress_update_post_social',     'description' => 'Update SEOPress social meta on a post: OpenGraph and Twitter title, description, and image.',                              'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_seopress_update_post_keywords',   'description' => 'Update the SEOPress target keywords on a post.',                                                                            'type' => 'write', 'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_seopress_get_content_analysis',   'description' => 'Get SEOPress content analysis results for a post (SEO checks and scores).',                                                'type' => 'read',  'api' => 'plugin_rest' ),
                    array( 'name' => 'wp_seopress_get_term_seo',           'description' => 'Get SEOPress SEO meta (title, description, robots) for a taxonomy term.',                                                  'type' => 'read',  'api' => 'plugin_rest' ),
                ),
            ),
            array(
                'slug'        => 'slim-seo',
                'name'        => 'Slim SEO',
                'description' => 'Read and update Slim SEO meta (title, description, OG/Twitter, robots) on posts and pages via the WordPress REST API.',
                'status'      => 'stable',
                'requires'    => 'Slim SEO must be active.',
                'detector'    => 'class:SlimSEO\\Container',
                'tools'       => array(
                    array( 'name' => 'wp_slimseo_get_post_seo',    'description' => 'Get Slim SEO meta for a post: title, description, OG/Twitter fields, and robots settings.', 'type' => 'read',  'api' => 'wp_rest' ),
                    array( 'name' => 'wp_slimseo_update_post_seo', 'description' => 'Update Slim SEO meta on a post: title, description, OG/Twitter fields, and robots settings.', 'type' => 'write', 'api' => 'wp_rest' ),
                ),
            ),
            array(
                'slug'        => 'the-seo-framework',
                'name'        => 'The SEO Framework',
                'description' => 'Read and update The SEO Framework meta (title, description, robots, OG/Twitter) on posts and pages.',
                'status'      => 'stable',
                'requires'    => 'The SEO Framework must be active.',
                'detector'    => 'function:tsf',
                'tools'       => array(
                    array( 'name' => 'wp_tsf_get_post_seo',    'description' => 'Get The SEO Framework meta for a post: title, description, robots, OG/Twitter fields.', 'type' => 'read',  'api' => 'php' ),
                    array( 'name' => 'wp_tsf_update_post_seo', 'description' => 'Update The SEO Framework meta on a post: title, description, robots, OG/Twitter fields.', 'type' => 'write', 'api' => 'php' ),
                ),
            ),
            
            
            
            array(
                'slug'        => 'learnpress',
                'name'        => 'LearnPress',
                'description' => 'Lets LearnPress\'s own abilities (courses, lessons, quizzes, enrollments) run with your Easy MCP key or connection, whether or not LearnPress\'s own MCP setting is on. LearnPress allows them to administrators only by default. Without it, LearnPress accepts them only through its own MCP endpoint and key. Choose which LearnPress abilities are available in the Learnpress group on this tab.',
                'status'      => 'stable',
                'requires'    => 'LearnPress must be active.',
                'detector'    => 'class:LearnPress',
                'tools'       => array(),
            ),
        );
    }

    





    public static function is_installed( array $group ): bool {
        if ( empty( $group['detector'] ) ) {
            return true;
        }
        $detector = $group['detector'];
        if ( strncmp( $detector, 'class:', 6 ) === 0 ) {
            return class_exists( substr( $detector, 6 ) );
        }
        if ( strncmp( $detector, 'function:', 9 ) === 0 ) {
            return function_exists( substr( $detector, 9 ) );
        }
        return true;
    }

    public static function get_all_tool_names(): array {
        $names = array();
        foreach ( self::get_groups() as $group ) {
            foreach ( $group['tools'] as $tool ) {
                $names[] = $tool['name'];
            }
        }
        return $names;
    }

    public static function get_tool_group_map(): array {
        $map = array();
        foreach ( self::get_groups() as $group ) {
            foreach ( $group['tools'] as $tool ) {
                $map[ $tool['name'] ] = $group['slug'];
            }
        }
        return $map;
    }
}
