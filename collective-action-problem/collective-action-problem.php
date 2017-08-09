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
			affiliation varchar(250),
			user_name varchar(250),
			ORCID varchar(30),
			website varchar(100),
			PRIMARY KEY (user_id,commit_id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql2 );
                
}

function create_new_commitment(){

     $commitment_content=$_POST['commit_content'];
     $threshold=$_POST['threshold'];
     foreach ($threshold as &$t){
         $t=intval($t);
     }
    collective_log('SIGNING NEW COMMITMENT data '.$commitment_content.' '.$threshold[0]);
     global $wpdb;
        $threshold_string = json_encode($threshold);
	if(!$commitment_content){
		return false;	
	}
	
	$table_name = $wpdb->prefix . 'psy_commitment';
        $date = date('Y-m-d H:i:s');
        
        $wpdb->insert(
                $table_name,
                array(
                    'time_created'=> $date,
                    'current_count'=> 0,
                    'content'=> $commitment_content,
                    'allowed_thresholds'=>json_encode($threshold)
                )
             );
        wp_redirect('/wp-admin/options-general.php?page=collactprob');
        return false;
}
add_action('wp_ajax_create_new_commitment', 'create_new_commitment');

add_action('wp_ajax_sign_commitment', 'sign_commitment');
add_action( 'init','load_custom_css');

function load_custom_css(){
    wp_register_style('collective-css', plugins_url('collective.css',__FILE__ ));
    wp_enqueue_style('collective-css');
    collective_log("Css Loaded");
}
/**
 * Used to render commitments using shortcode
 *
 * @return string
 */
function render_commitments(){
    ob_start();
if(is_user_logged_in()){


    global $commitments;
    $url=$_SERVER['REQUEST_URI'];
    global $current_user;
    $commitments =get_unsigned_commitments($current_user->ID);?>

    <div>
        <?php if(sizeof($commitments)==0){
            ?>
            <h1 align="center">Hurray, No More Commitments to Sign</h1>
            <?php

        } ?>
        <?php $success = get_query_var( 'success', false );
        if($success==true){
            ?>

            <h1>Thanks, You Successfully Signed A Commitment</h1>
            <?php
        }

        ?>


            <?php foreach($commitments as $commitment) : ?>


                    <div >
                        <form class="sel-form" action="<?php echo esc_url( admin_url('admin-ajax.php') ); ?>" method="post">
                            <div class="sel-container">
                                <input type="hidden" name="user_id" value="<?php echo $current_user->ID ?>">
                                <input type="hidden" name="user_name" value="<?php echo $current_user->first_name.' '.$current_user->last_name ?>">
                                <input type="hidden" name = "affiliation" value="<?php echo $current_user->description ?> ">
                                <input type="hidden" name = "orcid" value="<?php  echo $current_user->orcid ?> ">
                                <input type="hidden" name = "website" value="<?php  echo $current_user->website ?> ">
                                <input type="hidden" name="url"value="<?php echo $url?>">
                                <input type="hidden" name="commit_id" value="<?php echo $commitment['id']?>">
                                <div class="sel-column two-third"><p><?php echo $commitment['content']?></p></div>
                                <div class="sel-column one-third">
                                    <div class="sel-sel">
                                        <label>Select Threshold:</label>
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
                                    <div class="my_submit">
                                    <input id="col_submit" type="submit" name="submit" value="Sign"/>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </br>

            <?php endforeach; ?>

    </div>
    <?php
}
    return ob_get_clean();
}
wp_enqueue_style( 'collective', get_stylesheet_uri() );
add_shortcode("collective-commitments","render_commitments");

/**
 * Render One commitment using short code
 * @return string
 */
function render_one_commitment($atts = [], $content = null, $tag = ''){

    ob_start();
    if(is_user_logged_in()){

        collective_log("COMMITMENT ID FROM RENDER =".$atts['id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'psy_commitment';
        global $commitment;
        $url=$_SERVER['REQUEST_URI'];
        global $current_user;
        $commitment_id = $atts['id'];
        $commitment = $wpdb->get_results("SELECT * from $table_name WHERE id = $commitment_id",'ARRAY_A')[0];?>

        <div>
                <div >
                    <form class="sel-form" action="<?php echo esc_url( admin_url('admin-ajax.php') ); ?>" method="post">
                        <div class="sel-container">
                            <input type="hidden" name="user_id" value="<?php echo $current_user->ID ?>">
                            <input type="hidden" name="user_name" value="<?php echo $current_user->first_name.' '.$current_user->last_name ?>">
                            <input type="hidden" name = "affiliation" value="<?php echo $current_user->description ?> ">
                            <input type="hidden" name = "orcid" value="<?php echo $current_user->orcid ?> ">
                            <input type="hidden" name = "website" value="<?php echo $current_user->website ?> ">
                            <input type="hidden" name="url"value="<?php echo $url?>">
                            <input type="hidden" name="commit_id" value="<?php echo $commitment['id']?>">
                            <div class="sel-column two-third"><p><?php echo $commitment['content']?></p></div>
                            <div class="sel-column one-third">
                                <div class="sel-sel">
                                    <label>Select Threshold:</label>
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
                                <div class="my_submit">
                                    <input id="col_submit" type="submit" name="submit" value="Sign"/>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                </br>

        </div>
        <?php
    }
    return ob_get_clean();
}

add_shortcode("collective-one-commitment","render_one_commitment");

/*
 * This function is used to insert user commitment in to DB
 * 
 * Called only once per submit
 */
function sign_commitment(){
    $commitment_id=$_POST['commit_id'];
    $user_id=$_POST['user_id'];
    $user_threshold =$_POST['threshold'];
    $url=$_POST['url'];
    $affiliation =$_POST['affiliation'];
    $orcid = $_POST['orcid'];
    $website = $_POST['website'];
    $name = $_POST['user_name'];
    collective_log("USER NAME =".$name);

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
                    'status'=> 'PRIVATE',
                    'affiliation'=>$affiliation,
                    'user_name'=>$name,
                    'ORCID'=>$orcid,
                    'website'=>$website
                )
             );
        collective_Algorithm($commitment_id,$user_id,$user_threshold);
        wp_redirect($url);
        return false;
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
function get_commit_of_user($userid){
    global $wpdb;
    $table_name =$wpdb->prefix . 'psy_user_commitment';
    $table_name1 =$wpdb->prefix . 'psy_commitment';
    $commitments = $wpdb->get_results("SELECT a.time_signed,a.user_threshold,a.status,b.content
                    from $table_name a, $table_name1 b WHERE a.commit_id = b.id AND a.user_id = $userid ",'ARRAY_A');
    return $commitments;
}

function render_user_commitments(){
    ob_start();
    global $current_user;
    $user_commitments = get_commit_of_user($current_user->ID);?>
    <form>
        <h3 align="center">Your Commitments</h3>
        <h5> Total Signed Commitments : <?php echo sizeof($user_commitments) ?></h5>
        <hr>
        <table>
            <tr>

                <td>Content</td>
                <td>Time Signed At</td>
                <td>Selected Threshold</td>
                <td>Status</td>
            </tr>
            <ol>
                <?php
                foreach ($user_commitments as $c){ ?>

                    <tr>
                        <td><div><?php echo $c['content']?></div></td>
                        <td><div><?php echo $c['time_signed']?></div></td>
                        <td><div><?php echo $c['user_threshold'] ?></div></td>
                        <td><div><?php echo $c['status'] ?></div></td>

                    </tr>


                <?php }?>
            </ol>
        </table>
    </form>
<?php

    return ob_get_clean();
}

add_shortcode("user-commitments","render_user_commitments");

/**
 * Update a user's commitment. Only if it is not PUBLIC yet 
 * @global type $wpdb
 * @param type $userId
 * @param type $commitId
 * @param type $threshold
 * @return String eg: "PUBLIC"
 */

function get_public_commitments(){
    global $wpdb;
    $table_name1 = $wpdb->prefix . 'psy_user_commitment';
    $table_name2 = $wpdb->prefix . 'psy_commitment';
    $query = "SELECT a.content , b.time_signed,b.user_name,b.ORCID,b.website,b.affiliation FROM $table_name1 b , $table_name2 a 
              WHERE a.id = b.commit_id AND b.status = 'PUBLIC'";
    $commitments = $wpdb->get_results($query,"ARRAY_A");
    return $commitments;
}

function render_all_public_commitments(){
    ob_start();
$user_commitments = get_public_commitments()?>

    <form>
        <h3 align="center">All Signed Commitments</h3>
        <h5> Total Signed Commitments : <?php echo sizeof($user_commitments) ?></h5>
        <hr>
        <table>
            <tr>
                <td><h6>Signed By</h6></td>
                <td><h6>From</h6></td>
                <td><h6>ORCID</h6></td>
                <td><h6>Time Signed At</h6></td>
                <td><h6>Commitment</h6></td>
            </tr>
            <ol>
                <?php
                foreach ($user_commitments as $c){ ?>
                    <tr>
                        <td><a href= "http://<?php echo $c['website']?>" target="_blank"><?php echo $c['user_name'] ?></a></td>
                        <td><div><?php echo $c['affiliation'] ?></div></td>
                        <td><div><?php echo $c['ORCID'] ?></div></td>
                        <td><div><?php echo $c['time_signed']?></div></td>
                        <td><div><?php echo $c['content'] ?></div></td>
                    </tr>
                <?php }?>
            </ol>
        </table>
    </form>

<?php
    return ob_get_clean();
}
add_shortcode("public-commitments","render_all_public_commitments");

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


function get_unsigned_commitments($user_id){
    collective_log("Getting Unsigned Commits for user Id = ".$user_id);
    /*global $wpdb;
    $table_name1 =$wpdb->prefix . 'psy_commitment';
    $table_name2 =$wpdb->prefix . 'psy_user_commitment';
    $commitments = $wpdb->get_results("SELECT a.content,a.time_created,a.allowed_thresholds FROM $table_name1 a , $table_name2 b 
                                            WHERE a.id = b.commit_id AND b.user_id != $user_id");*/
    $available_commitments = get_all_commitments();
    $commitments=[];

    foreach($available_commitments as &$c){
        if(get_one_signed_commitment($c['id'],$user_id)){
            collective_log("GOT ONE Commitment".get_all_commitments($c['id'],$user_id)[0]['commit_id']);
            array_pop($c);
        }else{
            array_push($commitments,$c);
        }
    }
    collective_log("SIZE OF".sizeof($commitments));
    return $commitments;
}

function get_one_signed_commitment($commiy_id,$user_id){
    global $wpdb;
    $table_name2 =$wpdb->prefix . 'psy_user_commitment';
    $commitment = $wpdb->get_results("SELECT * FROM $table_name2 WHERE user_id =$user_id AND commit_id = $commiy_id",ARRAY_A);
    return $commitment;
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
    echo"</br></br>";
    echo " This is for the settings page of Collective Action Problem. ";
    echo "Use this page to render the settings of the Collective Action Problem Plugin.";
    echo "<hr> <form id='create_commit' method='post' action= '";

    echo esc_url( admin_url('admin-ajax.php') );
        $output="'>
                <script>
                function addNewThreshold(){
                  
                    // Create an input type dynamically
                    var element = document.createElement('input');
                    //Assign different attributes to the element.
                    element.setAttribute('type', 'text');
                    element.setAttribute('value', '');
                    element.setAttribute('name', 'threshold[]');
                    element.setAttribute('style', 'width:50px');
                    element.setAttribute('required','required');
                    
                    
                    // Ordered List
                    var l =document.createElement('li');
                    l.appendChild(element);
                
                // 'foobar' is the div id, where new fields are to be added
                    var div = document.getElementById('t_id');
                
                //Append the element in page (in span).
                    
                    div.appendChild(l);
                }
                </script>
                <h1 align='center'>Create New Commitment</h1><hr><br/>
                <h3 >Enter commitment description</h3>
                <textarea required='required' rows='4' form='create_commit' cols='50' id='c_content' name='commit_content' placeholder='Enter your Commitment description here..'></textarea>
                <div >
                <h3>Enter thresholds</h3>
                <ol id ='t_id'>
                    <li><input name='threshold[]' required='required' type='text' style='width:50px'></li>
                    <li><input name='threshold[]'required='required' type='text' style='width:50px'></li>
                </ol>
                </div>
                <a href='#' onclick='addNewThreshold()' value='Add'><div>Add more</div></a>";
        echo $output;
        echo wp_nonce_field('create_new_commitment','security-code-here');
        $output2 ="<input type='hidden' name='action' value='create_new_commitment'></br></br></br>
                <input type='submit' name='submit' value='Submit'/>
              </form> <hr>";
    echo $output2;
    $all_commitments = get_all_commitments();

     echo "</br><h1 align='center'>Existing Commitments</h1><hr></br>";
    $output3= "<table cellpadding='10' >
                <tr>
                   <td><h3>Content</h3></td>
                   <td><h3>Allowed Thresholds</h3></td>
                   <td></td>
                </tr>";
    foreach ($all_commitments as $commit){
        $output3 = $output3."
                <tr>
                    <td>".$commit['content']."</td>
                    <td>".$commit['allowed_thresholds']."</td>
                    <td><form method='post' action='". esc_url( admin_url('admin-ajax.php') ) ."'>
                   " . wp_nonce_field('remove_commitment','security-code-here')." 
                           <input type='hidden' name='id' value='".$commit['id']."'> 
                           <input type='hidden' name='action' value='remove_commitment'>
                           <input type='submit' value='Delete'>
                        </form>
                    </td>
                </tr>";
    }
    $output3 = $output3." </table>";
    echo $output3;



}
add_action('admin_menu', 'cap_add_submenu_page');

function remove_commitment(){
    collective_log('Removing commitment');
    $commitment_id=$_POST['id'];
    global $wpdb;
    $table_name2 =$wpdb->prefix . 'psy_commitment';
    $status = $wpdb->delete($table_name2,['id'=>$commitment_id]);
    wp_redirect('/wp-admin/options-general.php?page=collactprob');
    return false;
}
add_action('wp_ajax_remove_commitment', 'remove_commitment');

?>