<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

class DT_Users_Table extends DT_Metrics_Chart_Base
{

    //slug and title of the top menu folder
    public $base_slug = 'user-management'; // lowercase
    public $base_title;
    public $title;
    public $slug = 'table'; // lowercase
    public $js_object_name = 'wp_js_object'; // This object will be loaded into the metrics.js file by the wp_localize_script.
    public $js_file_name = '/dt-users/use.js'; // should be full file name plus extension
    public $permissions = [ 'list_users', 'manage_dt' ];
    public $namespace = 'user-management/v2';

    public function __construct() {
        parent::__construct();
        if ( !$this->has_permission() ){
            return;
        }

        $url_path = dt_get_url_path();
        if ( strpos( $url_path, 'user-management' ) !== false ) {
            add_filter( 'dt_metrics_menu', [ $this, 'add_menu' ], 20 );
        }
        if ( "$this->base_slug/$this->slug" === $url_path ) {
            add_filter( 'dt_metrics_menu', [ $this, 'base_menu' ], 20 ); //load menu links
            add_action( 'wp_enqueue_scripts', [ $this, 'base_scripts' ], 99 );
            add_filter( 'dt_templates_for_urls', [ $this, 'dt_templates_for_urls' ] );
            add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );
        }
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
        add_filter( 'script_loader_tag', [ $this, 'script_loader_tag' ], 10, 3 );

        add_filter( 'dt_users_fields', [ $this, 'dt_users_fields' ], 10, 1 );
    }

    public function script_loader_tag( $tag, $handle, $src ) {
        if ( $handle === 'dt_users_table' ) {
            $tag = '<script type="module" src="' . esc_url( $src ) . '"></script>'; //phpcs:ignore
        }
        return $tag;
    }

    public function dt_templates_for_urls( $template_for_url ) {
        $template_for_url['user-management/table'] = './dt-users/template-user-management.php';
        return $template_for_url;
    }

    public function base_menu( $content ) {
        return $content;
    }

    public function base_add_url( $template_for_url ) {
        return $template_for_url;
    }

    public function add_menu( $content ) {
        $content .= '<li><a href="'. esc_url( site_url( '/user-management/table/' ) ) .'" >' .  esc_html__( 'Table', 'disciple_tools' ) . '</a></li>';
        return $content;
    }

    public function scripts() {

        wp_enqueue_script( 'dt_users_table',
            DT_User_Management_Plugin::plugin_url() . 'dt-users/users-table.js',
            [
                'jquery'
            ],
            filemtime( DT_User_Management_Plugin::plugin_dir() . 'dt-users/users-table.js' ),
        );

        $fields = $this->user_fields();

        if ( isset( $fields['location_grid'] ) ){
            //used locations
            $locations = $this->get_used_user_locations();
            $fields['location_grid']['options'] = $locations;
        }

        wp_localize_script( 'dt_users_table', 'dt_users_table', [
            'translations' => [
                'go' => __( 'Go', 'disciple_tools' ),
                'search' => __( 'Search', 'disciple_tools' ),
                'users' => __( 'Users', 'disciple_tools' ),
                'showing_x_of_y' => __( 'Showing %1$s of %2$s', 'disciple_tools' ),
            ],
            'fields' => $fields,
        ] );

    }

    public function get_used_user_locations(){
        global $wpdb;
        $used_location_grids = $wpdb->get_results( $wpdb->prepare( "
            SELECT DISTINCT( g.grid_id ),
            CASE
                WHEN g.level = 0
                    THEN g.alt_name
                WHEN g.level = 1
                    THEN CONCAT( (SELECT country.alt_name FROM $wpdb->dt_location_grid as country WHERE country.grid_id = g.admin0_grid_id LIMIT 1), ' > ',
                g.alt_name )
                WHEN g.level >= 2
                    THEN CONCAT( (SELECT country.alt_name FROM $wpdb->dt_location_grid as country WHERE country.grid_id = g.admin0_grid_id LIMIT 1), ' > ',
                (SELECT a1.alt_name FROM $wpdb->dt_location_grid AS a1 WHERE a1.grid_id = g.admin1_grid_id LIMIT 1), ' > ',
                g.alt_name )
                ELSE g.alt_name
            END as label
            FROM $wpdb->dt_location_grid as g
            INNER JOIN (
                SELECT
                    g.grid_id
                FROM $wpdb->usermeta as um
                JOIN $wpdb->dt_location_grid as g ON g.grid_id=um.meta_value
                WHERE um.meta_key = %s
            ) as counter ON (g.grid_id = counter.grid_id)

            ORDER BY g.country_code, CHAR_LENGTH(label)
            ", $wpdb->prefix . 'location_grid' ),
            ARRAY_A
        );
        //key as index
        $used_location_grids = array_combine( wp_list_pluck( $used_location_grids, 'grid_id' ), $used_location_grids );

        return $used_location_grids;
    }

    public function add_api_routes() {
        register_rest_route( $this->namespace, '/get-users', [
            'methods'  => 'POST',
            'callback' => [ $this, 'get_users_endpoint' ],
            'permission_callback' => [ $this, 'has_permission' ]
        ] );
    }

    public function get_users_endpoint( WP_REST_Request $request ){
        $params = $request->get_params();
        return self::get_users( [ 'locale' ], $params );
    }

    public static function user_fields(){
        global $wpdb;
        $fields = [
            'ID' => [
                'table' => 'users_table',
                'label' => __( 'ID', 'disciple_tools' ),
                'type' => 'number',
            ],
            'user_email' => [
                'table' => 'users_table',
                'label' => __( 'Email', 'disciple_tools' ),
                'type' => 'text',
                'hidden' => true
            ],
            'user_login' => [
                'table' => 'users_table',
                'label' => __( 'Username', 'disciple_tools' ),
                'type' => 'text',
                'hidden' => true
            ],
            'display_name' => [
                'table' => 'users_table',
                'label' => __( 'Display Name', 'disciple_tools' ),
                'type' => 'text',
            ],
            'capabilities' => [
                'table' => 'usermeta_table',
                'key' => $wpdb->prefix . 'user_languages',
                'label' => __( 'Roles', 'disciple_tools' ),
                'options' => Disciple_Tools_Roles::get_dt_roles_and_permissions(),
                'type' => 'array_keys',
            ],
            'locale' => [
                'table' => 'usermeta_table',
                'key' => 'locale',
                'label' => __( 'Locale', 'disciple_tools' ),
                'options' => dt_get_available_languages( true ),
                'type' => 'key_select',
                'hidden' => true,
            ],
            'user_languages' => [
                'table' => 'usermeta_table',
                'key' => $wpdb->prefix . 'user_languages',
                'label' => __( 'Languages', 'disciple_tools' ),
                'options' => dt_get_option( 'dt_working_languages' ) ?: [],
                'type' => 'array',
            ],
            'location_grid' => [
                'table' => 'usermeta_table',
                'key' => $wpdb->prefix . 'location_grid',
                'label' => __( 'Locations', 'disciple_tools' ),
                'type' => 'location_grid',
            ],
            'user_status' => [
                'table' => 'usermeta_table',
                'key' => $wpdb->prefix . 'user_status',
                'label' => __( 'Status', 'disciple_tools' ),
                'options' => [
                    'active' => [ 'label' => __( 'Active', 'disciple_tools' ) ],
                    'away' => [ 'label' => __( 'Away', 'disciple_tools' ) ],
                    'inconsistent' => [ 'label' => __( 'Inconsistent', 'disciple_tools' ) ],
                    'inactive' => [ 'label' => __( 'Inactive', 'disciple_tools' ) ],
                ],
                'type' => 'key_select',
            ],
            'workload_status' => [
                'table' => 'usermeta_table',
                'key' => $wpdb->prefix . 'workload_status',
                'label' => __( 'Workload Status', 'disciple_tools' ),
                'options' => [
                    'active' => [
                        'label' => __( 'Accepting new contacts', 'disciple_tools' ),
                        'color' => '#4caf50'
                    ],
                    'existing' => [
                        'label' => __( "I'm only investing in existing contacts", 'disciple_tools' ),
                        'color' => '#ff9800'
                    ],
                    'too_many' => [
                        'label' => __( 'I have too many contacts', 'disciple_tools' ),
                        'color' => '#F43636'
                    ]
                ],
                'type' => 'key_select',
            ]
        ];

        $fields = apply_filters( 'dt_users_fields', $fields );
        return $fields;
    }

    public static function get_users( $meta_fields, $params = [] ){
        global $wpdb;

        $limit = 1000;
        if ( isset( $params['limit'] ) ){
            $limit = $params['limit'];
        }
        $filter = !empty( $params['filter'] ) ? $params['filter'] : [];

        $select = '';
        $joins = '';
        $where = '';

        $search = !empty( $params['search'] ) ? $params['search'] : '';
        if ( !empty( $params['search'] ) ){
            $search = esc_sql( $search );
            $columns = [ 'user_login', 'user_email', 'display_name' ];
            $where .= ' AND ( ';
            foreach ( $columns as $column ){
                $where .= " $column LIKE '%$search%' OR ";
            }
            $where = rtrim( $where, ' OR ' );
            $where .= ' ) ';
        }

        $user_fields = self::user_fields();
        $sort_sql = '';
        $sort = $params['sort'] ?? '';
        $dir = ( !empty( $sort ) && $sort[0] === '-' ) ? 'DESC' : 'ASC';
        $sort_field = str_replace( '-', '', $sort );
        if ( !empty( $sort_field ) ){
            $table = isset( $user_fields[$sort_field]['table'] ) ? $user_fields[$sort_field]['table'] : '';
            if ( in_array( $table, [ 'users_table', 'usermeta_table' ] ) ){
                $table = $user_fields[$sort_field]['table'];
                if ( $table === 'users_table' ){
                    $sort_sql = 'ORDER BY users.' . esc_sql( $sort_field ) . ' ' . $dir;
                } else {
                    $sort_sql = 'ORDER BY um_' . esc_sql( $sort_field ) . '.meta_value IS NULL, um_' . esc_sql( $sort_field ) . '.meta_value ' . $dir;
                }
            }
        }


        $fields_by_type = [];
        foreach ( $user_fields as $field_key => $field_value ){
            if ( !isset( $fields_by_type[ $field_value['type'] ] ) ){
                $fields_by_type[ $field_value['type'] ] = [];
            }
            $fields_by_type[ $field_value['type'] ][] = $field_key;
        }

        foreach ( $user_fields as $field_key => $field_value ){
            if ( $field_value['table'] === 'users_table' ){
                $select .= ", users.$field_key as $field_key";
            }
            if ( $field_value['table'] === 'usermeta_table' && isset( $field_value['key'] ) ){
                if ( $field_value['type'] === 'text' ){
                    $select .= ", um_$field_key.meta_value as $field_key";
                    $joins .= " LEFT JOIN $wpdb->usermeta as um_$field_key on ( um_$field_key.user_id = users.ID AND um_$field_key.meta_key = '{$field_value['key']}' ) ";
                }
                if ( $field_value['type'] === 'key_select' ){
                    $select .= ", um_$field_key.meta_value as $field_key";
                    $joins .= " LEFT JOIN $wpdb->usermeta as um_$field_key on ( um_$field_key.user_id = users.ID AND um_$field_key.meta_key = '{$field_value['key']}' ) ";
                    if ( !empty( $filter[$field_key] ) ){
                        $where .= $wpdb->prepare( " AND um_$field_key.meta_value LIKE %s ", $filter[$field_key] ); //phpcs:ignore
                    }
                }
                if ( $field_value['type'] === 'array' ){
                    $select .= ", um_$field_key.meta_value as $field_key";
                    $joins .= " LEFT JOIN $wpdb->usermeta as um_$field_key on ( um_$field_key.user_id = users.ID AND um_$field_key.meta_key = '{$field_value['key']}' ) ";
                    if ( !empty( $filter[$field_key] ) ){
                        $where .= $wpdb->prepare( " AND um_$field_key.meta_value LIKE %s ", '%'.$filter[$field_key].'%' ); //phpcs:ignore
                    }
                }
                if ( $field_value['type'] === 'array_keys' ){
                    if ( $field_key != 'capabilities' ){
                        $select .= ", um_$field_key.meta_value as $field_key";
                        $joins .= " LEFT JOIN $wpdb->usermeta as um_$field_key on ( um_$field_key.user_id = users.ID AND um_$field_key.meta_key = '{$field_value['key']}' ) ";
                    }
                    if ( !empty( $filter[$field_key] ) ){
                        $where .= $wpdb->prepare( " AND um_$field_key.meta_value LIKE %s ", '%'.$filter[$field_key].'%' ); //phpcs:ignore
                    }
                }

                if ( $field_value['type'] === 'location_grid' ){
                    $select .= ", GROUP_CONCAT(um_$field_key.meta_value) as $field_key";
                    $joins .= " LEFT JOIN $wpdb->usermeta as um_$field_key on ( um_$field_key.user_id = users.ID AND um_$field_key.meta_key = '{$field_value['key']}' ) ";
                    if ( !empty( $filter[$field_key] ) ){
                        $where .= $wpdb->prepare( " AND um_$field_key.meta_value LIKE %s ", $filter[$field_key] ); //phpcs:ignore
                    }
                }
            }
            if ( $field_value['table'] === 'postmeta' ){
                $meta_key = $field_value['meta_key'];
                $meta_value = $field_value['meta_value'];
                $select .= ", $field_key.count as $field_key";
                $inner = '';
                if ( !empty( $meta_key ) ){
                    $inner = "INNER JOIN $wpdb->postmeta pm2 ON ( pm2.post_id = pm.post_id AND pm2.meta_key = '$meta_key' )";
                }
                if ( !empty( $meta_key ) && !empty( $meta_value ) ){
                    $inner = "INNER JOIN $wpdb->postmeta pm2 ON ( pm2.post_id = pm.post_id AND pm2.meta_key = '$meta_key' AND pm2.meta_value = '$meta_value' )";
                }
                $joins .= " LEFT JOIN ( 
                    SELECT REPLACE(pm.meta_value, 'user-', '') as user_id, COUNT(pm.post_id) as count
                    FROM $wpdb->postmeta pm
                    $inner
                    WHERE pm.meta_key = 'assigned_to'
                    GROUP BY pm.meta_value
                ) $field_key ON ( $field_key.user_id = users.ID ) ";

                if ( $sort_field === $field_key ){
                    $sort_sql = " ORDER BY $field_key.count $dir ";
                }
            }
            if ( $field_value['table'] === 'dt_activity_log' ){
                $select .= ", $field_key.last_activity as $field_key";
                $joins .= " LEFT JOIN ( SELECT user_id,
                    log.hist_time as last_activity
                    FROM $wpdb->dt_activity_log as log
                    WHERE histid IN (
                        SELECT MAX( histid )
                        FROM $wpdb->dt_activity_log
                        GROUP BY user_id
                    )
                    GROUP BY user_id,  last_activity
                ) $field_key ON ( $field_key.user_id = users.ID ) ";
                if ( $sort_field === $field_key ){
                    $sort_sql = " ORDER BY $field_key.last_activity $dir ";
                }
            }
        }

        //phpcs:disable
        $users_query = $wpdb->get_results( $wpdb->prepare( "
            SELECT
                um_capabilities.meta_value as capabilities
                $select
            FROM $wpdb->users as users
            INNER JOIN $wpdb->usermeta as um_capabilities on ( um_capabilities.user_id = users.ID AND um_capabilities.meta_key = %s )
            " . $joins . "
            WHERE 1=1
            $where
            GROUP by users.ID, um_capabilities.meta_value
            $sort_sql
            LIMIT %d
        ", $wpdb->prefix . 'capabilities', $limit ),
        ARRAY_A );
        //phpcs:enable

        //get location names
        $location_grid_ids = [];
        foreach ( $users_query as $users ){
            if ( !empty( $users['location_grid'] ) ){
                $location_grid_ids = array_merge( $location_grid_ids, explode( ',', $users['location_grid'] ) );
            }
        }
        $location_grid_ids = array_unique( $location_grid_ids );
        $location_grid_ids_sql = dt_array_to_sql( $location_grid_ids );
        //phpcs:disable
        //already sanitized IN value
        $location_names_query = $wpdb->get_results( "
            SELECT alt_name, grid_id
            FROM $wpdb->dt_location_grid
            WHERE grid_id IN ( $location_grid_ids_sql )
        ", ARRAY_A );
        //phpcs:enable
        $location_names = [];
        foreach ( $location_names_query as $location ){
            $location_names[ $location['grid_id'] ] = $location['alt_name'];
        }

        foreach ( $users_query as &$user ){
            foreach ( $fields_by_type['array'] as $field_key ){
                if ( isset( $user[ $field_key ] ) ){
                    $user[ $field_key ] = unserialize( $user[ $field_key ] );
                }
            }
            foreach ( $fields_by_type['array_keys'] as $field_key ){
                if ( isset( $user[ $field_key ] ) ){
                    $user[ $field_key ] = unserialize( $user[ $field_key ] );
                    $user[ $field_key ] = array_keys( $user[ $field_key ] );
                }
            }
            foreach ( $fields_by_type['location_grid'] as $field_key ){

                if ( isset( $user[$field_key] ) ){
                    $grid_ids = explode( ',', $user[$field_key] );
                    $locations = [];
                    foreach ( $grid_ids as $id ){
                        $locations[] = [
                            'id' => $id,
                            'label' => $location_names[$id] ?? 'Unkonwn',
                        ];
                    }
                    $user[$field_key] = $locations;
                }
            }
        }

        //total users count
        $total_users = $wpdb->get_var( $wpdb->prepare( "
            SELECT count( users.ID)
            FROM $wpdb->users as users
            INNER JOIN $wpdb->usermeta as um_capabilities on ( um_capabilities.user_id = users.ID AND um_capabilities.meta_key = %s )
            WHERE 1=1
            ", $wpdb->prefix . 'capabilities' ) );

        return [
            'users' => apply_filters( 'dt_users_list', $users_query, $params ),
            'total_users' => intval( $total_users ),
        ];
    }


    public function dt_users_fields( $fields ){
        $fields['number_new_assigned'] = [
            'label' => 'Accept Needed',
            'type' => 'number',
            'table' => 'postmeta',
            'meta_key' => 'overall_status',
            'meta_value' => 'assigned',
        ];
        $fields['number_active'] = [
            'label' => 'Active',
            'type' => 'number',
            'table' => 'postmeta',
            'meta_key' => 'overall_status',
            'meta_value' => 'active',
        ];
        $fields['number_assigned_to'] = [
            'label' => 'Assigned',
            'type' => 'number',
            'table' => 'postmeta',
            'meta_key' => 'assigned_to',
            'hidden' => true,
        ];
        $fields['number_update'] = [
            'label' => 'Update Needed',
            'type' => 'number',
            'table' => 'postmeta',
            'meta_key' => 'requires_update',
            'meta_value' => '1',
        ];
        $fields['last_activity'] = [
            'label' => 'Last Activity',
            'type' => 'date',
            'table' => 'dt_activity_log',
            'meta_key' => 'activity_date',
        ];

        return $fields;
    }
}
new DT_Users_Table();
