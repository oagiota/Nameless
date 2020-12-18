<?php
/*
 *	Made by Samerton
 *  https://github.com/NamelessMC/Nameless/
 *  NamelessMC version 2.0.0-pr8
 *
 *  License: MIT
 *
 *  Delete topic
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
if (!isset($_GET["tid"]) || !is_numeric($_GET["tid"])) {
    Redirect::to(URL::build('/forum'));
    die();
} else {
    $topic_id = $_GET["tid"];
}

// Check topic exists
$topic = $queries->getWhere('topics', array('id', '=', $topic_id));

if (!count($topic)) {
    Redirect::to(URL::build('forum'));
    die();
}

$topic = $topic[0];

if ($forum->canModerateForum($topic->forum_id, $user->getAllGroupIds())) {
    try {
        $queries->update('topics', $topic_id, array(
            'deleted' => 1
        ));
        //TODO: TOPIC
        Log::getInstance()->log(Log::Action('forums/topic/delete'), $topic_id);

        $posts = $queries->getWhere('posts', array('topic_id', '=', $topic_id));

        if (count($posts)) {
            foreach ($posts as $post) {
                $queries->update('posts', $post->id, array(
                    'deleted' => 1
                ));
            }
        }

        // Update latest posts in forums
        $recent_post = DB::getInstance()->query('SELECT id, topic_reply_date, topic_last_user, forum_id FROM nl2_topics WHERE forum_id = ? AND `deleted` = 0 ORDER BY topic_reply_date DESC LIMIT 1', array($topic->forum_id))->first();

        if ($topic->forum_id !== $recent_post->forum_id) {
            $queries->update('forums', $topic->forum_id, array(
                'last_post_date' => $recent_post ? $recent_post->topic_reply_date : null,
                'last_user_posted' => $recent_post ? $recent_post->topic_last_user : null,
                'last_topic_posted' => $recent_post ? $recent_post->id : null,
            ));
            $forum->updateForumLatestPosts($topic->forum_id);
        }

        Redirect::to(URL::build('/forum'));
        die();
    } catch (Exception $e) {
        die($e->getMessage());
    }
} else {
    Redirect::to(URL::build('/forum'));
    die();
}
