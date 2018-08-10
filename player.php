<?php
include 'list.php';

ini_set("display_errors", "On"); 
function curl_get($url)
{
    $refer = "http://music.163.com/";
    $header[] = "Cookie: " . "appver=1.5.0.75771;";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($ch, CURLOPT_REFERER, $refer);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

// 歌单内容
function get_playlist_info($playlist_id)
{
    $url = "http://music.163.com/api/playlist/detail?id=" . $playlist_id;
    return curl_get($url);
}

// 单曲内容
function get_music_info($music_id)
{
    $url = "http://music.163.com/api/song/detail/?id=" . $music_id . "&ids=%5B" . $music_id . "%5D";
    return curl_get($url);
}

// 歌词
function get_music_lyric($music_id)
{
    $url = "http://music.163.com/api/song/lyric?os=pc&id=" . $music_id . "&lv=-1&kv=-1&tv=-1";
    return curl_get($url);
}

// 随机选歌
function rand_music()
{
    global $player_list;
    $sum = count($player_list);
    $id = $player_list[rand(0, $sum - 1)];
    return $id;
}

// 播放过的歌不再播放
function get_music_id()
{
    $played = isset($_COOKIE["played"]) ? json_decode($_COOKIE["played"]) : null;
    $id = rand_music();
    if ($played != null) {
        global $player_list;
        $sum = count($player_list);
        if ($sum >= 2) {
            $sum = $sum * 0.5;
        } else {
            $sum -= 1;
        }
        while (in_array($id, $played)) {
            $id = rand_music();
        }
        if (count($played) >= $sum) {
            array_shift($played);
        }
    }
    $played[] = $id;
    setcookie("played", json_encode($played), time() + 3600);
    return $id;
}

// 获取播放列表
foreach ($playlist_list as $key) {
    $json = get_playlist_info($key);
    $arr = json_decode($json, true);
    foreach ($arr["result"]["tracks"] as $key2) {
        $id = $key2["id"];
        if (!in_array($id, $player_list)) {
            $player_list[] = $id;
        }
    }
}

//获取数据
$id = get_music_id();
$music_info = json_decode(get_music_info($id), true);
$lrc_info = json_decode(get_music_lyric($id), true);

//处理音乐信息
$play_info["cover"] = $music_info["songs"][0]["album"]["picUrl"];
$play_info["mp3"] = shell_exec('python api.py '.$id);
$play_info["music_name"] = $music_info["songs"][0]["name"];
foreach ($music_info["songs"][0]["artists"] as $key) {
    if (!isset($play_info["artists"])) {
        $play_info["artists"] = $key["name"];
    } else {
        $play_info["artists"] .= "," . $key["name"];
    }
}

//处理歌词
if (isset($lrc_info["lrc"]["lyric"])) {
    $lrc = explode("\n", $lrc_info["lrc"]["lyric"]);
    array_pop($lrc);
    foreach ($lrc as $rows) {
        $row = explode("]", $rows);
        if (count($row) == 1) {
            $play_info["lrc"] = "no";
            break;
        } else {
            $lyric = array();
            $col_text = end($row);
            array_pop($row);
            foreach ($row as $key) {
                $time = explode(":", substr($key, 1));
                $time = $time[0] * 60 + $time[1];
                $play_info["lrc"][$time] = $col_text;
            }
        }
    }
} else {
    $play_info["lrc"] = "no";
}

// 处理翻译
if (isset($lrc_info["tlyric"]["lyric"])) {
    $lrc = explode("\n", $lrc_info["tlyric"]["lyric"]);
    array_pop($lrc);
    foreach ($lrc as $rows) {
        $row = explode("]", $rows);
        if (count($row) == 1) {
            $play_info["tlrc"] = "no";
            break;
        } else {
            $lyric = array();
            $col_text = end($row);
            array_pop($row);
            foreach ($row as $key) {
                $time = explode(":", substr($key, 1));
                $time = $time[0] * 60 + $time[1];
                $play_info["tlrc"][$time] = $col_text;
            }
        }
    }
} else {
    $play_info["tlrc"] = "no";
}
echo json_encode($play_info);
