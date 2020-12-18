<?php
/*
 *	Made by Samerton
 *  https://github.com/NamelessMC/Nameless/
 *  NamelessMC version 2.0.0-pr8
 *
 *  License: MIT
 *
 *  Delete post page
 */

if (!$user->isLoggedIn()) {
    Redirect::to(URL::build('/forum'));
    die();
}

require_once(ROOT_PATH . '/modules/Forum/classes/Forum.php');

// Always define page name
define('PAGE', 'forum');

$forum = new Forum();

// Check params are set
if (!isset($_GET['pid']) || !is_numeric($_GET['pid'])) {
    Redirect::to(URL::build('/forum'));
    die();
}

// Get post and forum ID
$post = $queries->getWhere('posts', array('id', '=', $_GET['pid']));
if (!count($post)) {
    Redirect::to(URL::build('/forum'));
    die();
}
$post = $post[0];

$topic_id = $post->topic_id;
$forum_id = $post->forum_id;

if ($forum->canModerateForum($forum_id, $user->getAllGroupIds())) {
    if (Input::exists()) {
        if (Token::check()) {
            if (isset($_POST['tid'])) {
                // Is it the OP?
                if (isset($_POST['number']) && Input::get('number') == 10) {
                    try {
                        $queries->update('topics', Input::get('tid'), array(
                            'deleted' => 1
                        ));
                        Log::getInstance()->log(Log::Action('forums/post/delete'), Input::get('tid'));
                        $opening_post = 1;
                    } catch (Exception $e) {
                        die($e->getMessage());
                    }
                    $redirect = URL::build('/forum'); // Create a redirect string
                } else {
                    $redirect = URL::build('/forum/topic/' . Input::get('tid'));
                }
            } else $redirect = URL::build('/forum/search/', 'p=1&s=' . htmlspecialchars($_POST['search_string']));

            try {
                $queries->update('posts', Input::get('pid'), array(
                    'deleted' => 1
                ));

                $posts = DB::getInstance()->query('SELECT * FROM nl2_posts WHERE topic_id = ? AND deleted = 0', array($topic_id))->results();

                if (isset($opening_post)) {
                    if (count($posts)) {
                        DB::getInstance()->createQuery('UPDATE nl2_posts SET deleted = 1 WHERE topic_id = ?', $topic_id);
                    }

                    $recent_post = DB::getInstance()->query('SELECT topic_id, created, post_date, post_creator FROM nl2_posts WHERE deleted = 0 AND forum_id = ? ORDER BY created DESC LIMIT 1', array($forum_id))->first();

                    $queries->update('forums', $forum_id, array(
                        'last_post_date' => $recent_post ? ($recent_post->created ? $recent_post->created : strtotime($recent_post->post_date)) : null,
                        'last_user_posted' => $recent_post ? $recent_post->post_creator : null,
                        'last_topic_posted' => $recent_post ? $recent_post->topic_id : null
                    ));

                    echo '<pre>', print_r($recent_post), '</pre>';
                } else {
                    $forum->updateTopicLatestPost($topic_id, $forum_id);
                }

                echo '<pre>', print_r(DB::getInstance()->query('SELECT * FROM nl2_forums WHERE id = ?', array($forum_id))->results()), '</pre>';

                $forum->updateForumLatestPosts($forum_id);

                die();
                Redirect::to($redirect);
                die();
            } catch (Exception $e) {
                die($e->getMessage());
            }
        } else {
            Redirect::to(URL::build('/forum/topic/' . Input::get('tid')));
            die();
        }
    } else {
        echo 'No post selected';
        die();
    }
} else {
    Redirect::to(URL::build('/forum'));
    die();
}
