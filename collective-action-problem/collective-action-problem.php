<?php
/**
 * Plugin Name: Collective Action Problem
 * Plugin URI: http://www.researchtransparency.org/
 * Description: This Plugin Solves The Collective Action Problem.
 * Version: 1.0.0
 * Author: Shobhit Agrawal, Abhishek Kapoor, Ankit Gupta
 * Author URI: http://www.researchtransparency.org/
 */

$GLOBALS[ 'wp_log' ][ 'Collective Action Problem' ][] = 'Enable Logging 1';
$GLOBALS[ 'wp_log_plugins' ][] = 'Collective Action Problem';

function collective_log( $message ) {
    if ( defined( 'WP_DEBUG_LOG' ) ){
        $GLOBALS[ 'wp_log' ][ 'Collective Action Problem' ][] = $message;
    }
}

register_activation_hook( __FILE__, 'my_plugin_create_db' );
add_action('admin_init','test_collective');
function my_plugin_create_db() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table_name1 = $wpdb->prefix . 'psy_commitment';

	$sql1 = "CREATE TABLE $table_name1 (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		time_created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		max_threshold smallint(5) NOT NULL,
		current_count smallint(5) NOT NULL,
		content varchar(500) NOT NULL,
		UNIQUE KEY id (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql1 );

	$table_name2 = $wpdb->prefix . 'psy_user_commitment';

		$sql2 = "CREATE TABLE $table_name2 (
			user_id mediumint(9) NOT NULL,
			commit_id mediumint(9) NOT NULL,
			time_signed datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			user_threshold smallint(5) NOT NULL,
			status varchar(20) NOT NULL,
			PRIMARY KEY (user_id,commit_id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql2 );

}

function create_new_commitment($commitment_content,$max_threshold){
     collective_log("Creating a New Commitment to be signed with Threshold = ".$max_threshold);
     global $wpdb;

	if(!$commitment_content || !$max_threshold){
		return false;
	}

	$table_name = $wpdb->prefix . 'psy_commitment';
        $date = date('Y-m-d H:i:s');

        $wpdb->insert(
                $table_name,
                array(
                    'time_created'=> $date,
                    'max_threshold'=> $max_threshold,
                    'current_count'=> 0,
                    'content'=> $commitment_content
                )
             );
}

/*
 * This function is used to insert user commitment in to DB
 *
 * Called only once per submit
 */
function sign_commitment($commitment_id,$user_id,$user_threshold){
     collective_log("Commitment signed by user with ID = ".$user_id." commitment ID = ".$commitment_id);

    global $wpdb;

	if(!$commitment_id || !$user_id){
		return false;
	}

	$table_name = $wpdb->prefix . 'psy_user_commitment';
        $date = date('Y-m-d H:i:s');

        $wpdb->insert(
                $table_name,
                array(
                    'user_id'=>$user_id,
                    'commit_id'=>$commitment_id,
                    'time_signed'=> $date,
                    'user_threshold'=> $user_threshold,
                    'status'=> 'PRIVATE'
                )
             );
}

/*
 * Get Maximum threshold set by admin for a commitment
 */
function get_max_threshold($commit_id){
    global $wpdb;
    $table_name = $wpdb->prefix . 'psy_commitment';
    $max_threshold = $wpdb->get_results("SELECT max_threshold from $table_name WHERE id = $commit_id ",'ARRAY_A');
    return $max_threshold[0]['max_threshold'];
}

// Get current threshold for a commitment
function get_current_count($commit_id){
    global $wpdb;
    $table_name =$wpdb->prefix . 'psy_commitment';
    $current_count = $wpdb->get_results("SELECT current_count from $table_name WHERE id = $commit_id",'ARRAY_A');
    return $current_count[0]['current_count'];
}

/*
 * Count number of users for given commitment with given user_threshold
 */
function count_users_with_threshold($threshold, $commit_id){
    global $wpdb;
    $wpdb->show_errors();
    $table_name = $wpdb->prefix . 'psy_user_commitment';
    $query = "SELECT COUNT(*) from $table_name WHERE commit_id= $commit_id AND user_threshold = $threshold";

    $count = $wpdb->get_results($wpdb->prepare($query),'ARRAY_A');
    return $count[0]["COUNT(*)"];
}

/*
 * get array of all user ids to be updated
 */
function get_users_array_to_sign($threshold, $commit_id){
    global $wpdb;
    $table_name = $wpdb->prefix . 'psy_user_commitment';
    $result = $wpdb->get_results("SELECT user_id from $table_name WHERE commit_id=$commit_id AND user_threshold=$threshold",'ARRAY_A');
    $user_ids =[];
    foreach ($result as $key => $value) {
        array_push($user_ids, $value['user_id']);
    }
    return $user_ids;
}

/*
 * update the status of user_commitment to SIGNED
 */
function update_status_to_signed($user_id, $commit_id){
    global $wpdb;
    $table_name = $wpdb->prefix . 'psy_user_commitment';

     $status = $wpdb->update(
            $table_name,
            array(
                'status'=> 'SIGNED'
            ),
            array(
                'commit_id'=> $commit_id,
                'user_id'=> $user_id
            )
         );

    return $status;
}

/*
 * update the status of all users in user_commitment to PUBLIC
 */
function update_status_to_public($commit_id){
    global $wpdb;
    $table_name = $wpdb->prefix . 'psy_user_commitment';

    $status = $wpdb->update(
            $table_name,
            array(
                'status'=> 'PUBLIC'
            ),
            array(
                'commit_id'=> $commit_id
            )
         );
    return $status;
}

/*
 * update current counter for a commitment
 */
function update_current_counter($commit_id,$count){
    collective_log("inside update counter");
    global $wpdb;
    $table_name =$wpdb->prefix . 'psy_commitment';

    $status = $wpdb->update(
            $table_name,
            array(
                'current_count'=> $count
            ),
            array(
                'id'=> $commit_id
            )
            );
    collective_log($status);
   return $status;
}

/**
 *
 * @global type $wpdb
 * @param Integer $userid
 * @return Array rows indexed from 0
 */
function get_public_commit_of_user($userid){
    global $wpdb;
    $table_name =$wpdb->prefix . 'psy_user_commitment';
    $commitments = $wpdb->get_results("SELECT * from $table_name WHERE user_id = $userid AND status ='PUBLIC' ",'ARRAY_A');
    return $commitments;
}

**
 * Update a user's commitment. Only if it is not PUBLIC yet 
 * @global type $wpdb
 * @param type $userId
 * @param type $commitId
 * @param type $threshold
 * @return String eg: "PUBLIC"
 */
function update_user_commitment($userId,$commitId,$threshold){
    // TODO: Not Yet complete
    global $wpdb;
    $table_name = $wpdb->prefix . 'psy_user_commitment';
    $result = $wpdb->get_results("SELECT status from $table_name WHERE user_id = $userId AND commit_id = $commitId",'ARRAY_A');
    if($result[0]["status"]== "PUBLIC"){
        return false;
    }
    return $result[0]["status"];
}

function test_collective(){
    collective_log("inside Test collection");
    create_new_commitment('blasfamy', 99);

}

//Addition of further functionality
