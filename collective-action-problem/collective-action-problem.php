
<?php
/**
 * Plugin Name: Collective Action Problem
 * Plugin URI: http://www.researchtransparency.org/
 * Description: This Plugin Solves The Collective Action Problem.
 * Version: 1.0.0
 * Author: Shobhit Agrawal
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
		allowed_thresholds varchar(100) NOT NULL,
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

function create_new_commitment($commitment_content,$max_threshold,$threshold){
     collective_log("Creating a New Commitment to be signed with Threshold = ".$max_threshold);
     global $wpdb;
        $threshold_string = json_encode($threshold);
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
                    'content'=> $commitment_content,
                    'allowed_thresholds'=>json_encode($threshold)
                )
             );
        return true;
}

add_action('wp_ajax_sign_commitment', 'sign_commitment');

/**
 * Used to render commitments using shortcode
 *
 * @return string
 */
function render_commitments(){
    ob_start();
    ?><?php
    global $commitments;
    global $current_user;
    $commitments =get_all_commitments();?>

    <div>
        <ol>

            <?php foreach($commitments as $commitment) : ?>
                <li>

                    <form style="background-color:lightgrey" action="<?php echo esc_url( admin_url('admin-ajax.php') ); ?>" method="post">
                        <input type="hidden" name="user_id" value="<?php echo $current_user->ID ?>">
                        <input type="hidden" name="commit_id" value="<?php echo $commitment['id']?>">
                        <div><h1><?php echo $commitment['content']?></h1></div>
                        <div>
                            <label for="threshold">Select Threshold</label>
                            <select  name="threshold">
                                <?php
                                foreach (json_decode($commitment['allowed_thresholds']) as $threshold){
                                    ?>
                                    <option value="<?php echo intval($threshold) ?>"><?php echo intval($threshold)?></option>
                                    <?php
                                }
                                ?>
                            </select>
                        </div>
                        <?php wp_nonce_field('sign_commitment','security-code-here'); ?>
                        <input type="hidden" name="action" value="sign_commitment">
                        <input align="right" type="submit" name="submit" value="Sign"/>
                    </form>
                </br>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode("collective-commitments","render_commitments");

/*
 * This function is used to insert user commitment in to DB
 * 
 * Called only once per submit
 */
function sign_commitment(){
    $commitment_id=$_POST['commit_id'];
    $user_id=$_POST['user_id'];
    $user_threshold =$_POST['threshold'];

    /*$commitment_id=1;
    $user_id=31;
    $user_threshold =10;*/
     collective_log("Commitment signed by user with ID = ".$user_id." commitment ID = ".$commitment_id." 
     with Threshold =".$user_threshold);
   
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
        collective_Algorithm($commitment_id,$user_id,$user_threshold);
        return true;
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
    collective_log("CURRENT COUNT GOT CALLED".$commit_id);
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
function update_status_to_public($commit_id,$user_id){
    global $wpdb;
    $table_name = $wpdb->prefix . 'psy_user_commitment';
 
    $status = $wpdb->update(
            $table_name,
            array(
                'status'=> 'PUBLIC'
            ),
            array(
                'commit_id'=> $commit_id,
                'user_id'=>$user_id
            )
         );
    return $status;
}

/*
 * update current counter for a commitment
 */
function update_current_counter($commit_id,$count){
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
/**
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

/**
 * count the number of users who have signed a commitment
 * @param $commitment_id
 * @return int: count of users
 */
function get_signedUsers_for_commitment($commitment_id){
    global $wpdb;
    $wpdb->show_errors();
    $table_name = $wpdb->prefix . 'psy_user_commitment';
    $query = "SELECT COUNT(*) from $table_name WHERE commit_id= $commitment_id";

    $count = $wpdb->get_results($wpdb->prepare($query),'ARRAY_A');
    return $count[0]["COUNT(*)"];
}

function collective_Algorithm($commitment_id,$user_id,$user_threshold){
    global $wpdb;
    $table_name = $wpdb->prefix . 'psy_commitment';
    $commitment = $wpdb->get_results("SELECT * from $table_name WHERE id = $commitment_id",'ARRAY_A');
    $thresholds = json_decode($commitment[0]['allowed_thresholds']);

    $current_signed_user_count = intval(get_signedUsers_for_commitment($commitment_id));
    $current_count = get_current_count($commitment_id);
    collective_log("current count =".$current_count);
    
    foreach ($thresholds as $value) {
            if($current_signed_user_count>=$value){
                    $users =get_users_array_to_sign($value,$commitment_id);
                    foreach($users as $u){
                        collective_log("updating to PUBLIC for user id =".$u);
                        update_status_to_public($commitment_id,$u);
                    }
                    $current_count = $current_count + sizeof($users);
            }
    }
    return true;
}

function get_all_commitments(){
    global $wpdb;
    $table_name =$wpdb->prefix . 'psy_commitment';
    $commitments = $wpdb->get_results("SELECT * from $table_name",'ARRAY_A');

    return $commitments;
}


/**
 * Just a basic test function what will run on page reload
 * Call any other function from within and use collective_log() to log the result in a separate file
 */
function test_collective(){
    if ( is_user_logged_in() ) {
    // Current user is logged in,
    // so let's get current user info
    $current_user = wp_get_current_user();
    // User ID
    $user_id = $current_user->ID;
    collective_log("USER ID =".$user_id);
}
    collective_log("inside Test collection");
    //collective_log(sign_commitment());
    //collective_log(update_status_to_public(15,1));
    //collective_log("count of users =".get_signedUsers_for_commitment(15));
    //collective_log(sign_commitment(29,100,11));
    //collective_Algorithm(1,12,10);
   /* collective_log(create_new_commitment("We am going to sign for Food sharing",11,[
        "10",
        "15",
        "20",
        "25",
        "30"
    ]));*/
    //collective_log("CURRENT COUNTSS".get_current_count(11));
}

//Addition of further functionality
//Addition of subpages
function cap_add_submenu_page(){
    add_submenu_page( 'options-general.php',
        'Coll. Act. Prob.',
        'Coll. Act. Prob.',
        'manage_options',
        'collactprob',
        'cap_settings_callback');   //Callback to render settings page
}
function cap_settings_callback(){
    echo "This is for the settings page of Collective Action Problem. ";
    echo "Use this page to render the settings of the Collective Action Problem Plugin.";
    $output ="<form id='create_commit'>
                <h1>Create Commitment</h1><br/>
                <h3 >Enter commitment description</h3>
                <textarea rows='4' form='create_commit' cols='50' id='c_content' name='commit_content' placeholder='Enter your Commitment description here..'></textarea>
              </form>";
    echo $output;
}
add_action('admin_menu', 'cap_add_submenu_page');




