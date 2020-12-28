<!--
    4107056019 廖柏丞 第八次作業 12/23
    4107056019 Bocheng Liao The Eighth Homework 12/23
-->
<?php
session_start();
$islogin = !empty($_SESSION['login']);

class DB{
    public $conn;
    public $pre_command;
    function __construct(){
        $this->conn = new mysqli('localhost', 'iw3htp', 'password', 'major');
        if($this->conn->connect_error){
            die("Connect fail");
        }
        $this->conn->query('SET NAMES UTF8');
        return $this->conn;
    }

    function query($command, ...$args){
        foreach($args as $k => $v){
            if(gettype($v) === "string"){
                $command = preg_replace("/\?/",
                "\"".$this->conn->real_escape_string($v)."\"", $command, 1);
            }else{
                $command = preg_replace("/\?/",
                $this->conn->real_escape_string($v), $command, 1);
            }
        }
        $this->pre_command = $command;
        return $this->conn->query($command);
    }

    function close(){
        $this->conn->close();
    }
}

class BlackJack{
    public $player_remainder;
    public $bets;
    public $last_bets;

    private $user_id;
    private $round_counter;
    private $cardlist = [];

    private $banker_remainder;
    public $record_view;

    public $banker_cardlist = [];
    public $player_cardlist = [];

    public $cheat = FALSE;

    public $can_signal = TRUE;
    public $is_stop = FALSE;
    public $alert = "";

    const PLAYER = 1;
    const BANKER = 2;
    const LATEST = 3;

    function __construct($user_id){
        $this->bets = 10;
        $this->last_bets = $this->bets;
        $this->user_id = $user_id;
        $this->record_view = null;

        // init by database
        $db = new DB;
        $res = $db->query("SELECT max(round) as m FROM record WHERE id = ?",
        $this->user_id);
        $row = $res->fetch_assoc();
        $this->round_counter = (gettype($row['m']) === "NULL")? 0 : $row['m'];

        $res = $db->query("SELECT remainder as r FROM account WHERE id = ?",
        $this->user_id);
        $this->player_remainder = $res->fetch_assoc()['r'];

        $res = $db->query("SELECT remainder as r FROM account WHERE id = ?", "banker");
        $this->banker_remainder = $res->fetch_assoc()['r'];
        $db->close();
    }

    function increase_bets($n){
        if($n == -1){
            $this->bets = 10;
        }else if($this->bets + $n > $this->player_remainder){
            return;
        }else{
            $this->bets += $n;
        }
        $this->last_bets = $this->bets;
    }

    function init_cardlist(){
        //  0 - 12 C
        // 13 - 25 D
        // 26 - 38 H
        // 39 - 52 S
        // 53 means back card
        if(count($this->cardlist) < 28){
            for($i = 0; $i < 52; $i++){
                $this->cardlist[$i] = $i;
            }
            shuffle($this->cardlist);
        }
        $this->banker_cardlist = [];
        $this->player_cardlist = [];
    }

    function sum($list){
        $sum = 0;
        $A = 0;
        $tmp;
        for($i = 0; $i < count($list); $i++){
            $tmp = $list[$i] % 13;
            if($tmp == 0){       // A
                $A++;
                $sum += 1;
            }else if($tmp == 10 || $tmp == 11 || $tmp == 12){  // J Q K
                $sum += 10;
            }else{
                $sum += $tmp + 1;
            }
        }

        // when A = 1  11, 1
        // when A = 2  11 + 1, 1 + 1
        // when A = 3  11 + 1 + 1, 1 + 1 + 1
        // First, see A as 1
        // Then, try to plus 10

        if($A >= 1 && $sum + 10 <= 21) $sum += 10;

        return $sum;
    }

    function toggle_cheat(){
        $this->cheat = !$this->cheat;
    }

    function banker_play(){
        if(!$this->cheat){
            while($this->sum($this->banker_cardlist) < 17){
                $card = array_pop($this->cardlist);
                array_push($this->banker_cardlist, $card);
            }
            if($this->sum($this->banker_cardlist) > 21) return;
            while($this->sum($this->banker_cardlist) <
                $this->sum($this->player_cardlist)){
                $card = array_pop($this->cardlist);
                array_push($this->banker_cardlist, $card);
            }
            if($this->sum($this->banker_cardlist) > 21) return;
        }else{
            $banker_list_bak = array_slice($this->banker_cardlist, 0);
            $banker_list_bak_len = count($banker_list_bak);
            $now_player_sum = $this->sum($this->player_cardlist);

            // 不用挑已經勝利了
            if($this->sum($this->banker_cardlist) > 17 &&
               $this->sum($this->banker_cardlist) > $now_player_sum){
                return;
            }

            // 挑一張
            // 是否存在一張牌可直接獲勝
            for($i = 0; $i < count($this->cardlist); $i++){
                $this->banker_list_bak[$banker_list_bak_len] = $this->cardlist[$i];
                $tmp = $this->sum($banker_list_bak);
                if($tmp >= $now_player_sum && $tmp <= 21 && $tmp >= 17){
                    array_push($this->banker_cardlist, $this->cardlist[$i]);
                    array_splice($this->cardlist, $i, 1);
                    return;
                }
            }

            // 挑兩張
            // 是否存在挑兩張牌可直接獲勝
            for($i = 0; $i < count($this->cardlist); $i++){
                for($j = $i + 1; $j < count($this->cardlist); $j++){
                    $banker_list_bak[$banker_list_bak_len] = $this->cardlist[$i];
                    $banker_list_bak[$banker_list_bak_len + 1] = $this->cardlist[$j];
                    $tmp = $this->sum($banker_list_bak);
                    if($tmp >= $now_player_sum && $tmp <= 21 && $tmp >= 17){
                        array_push($this->banker_cardlist, $this->cardlist[$i]);
                        array_push($this->banker_cardlist, $this->cardlist[$j]);
                        array_splice($this->cardlist, $i, 1);
                        array_splice($this->cardlist, $j, 1);
                        return;
                    }
                }
            }

            // 使用大絕招
            // 重新洗牌之術
            $mark = array();
            for($i = 0; $i < 52; $i++){
                $mark[$i] = TRUE;
            }
            for($i = 0; $i < count($this->banker_cardlist); $i++){
                $mark[$this->banker_cardlist[$i]] = FALSE;
            }
            for($i = 0; $i< count($this->player_cardlist); $i++){
                $mark[$this->player_cardlist[$i]] = FALSE;
            }
            $this->cardlist = array();
            for($i = 0; $i < 52; $i++){
                if($mark[$i]){
                    array_push($this->cardlist, $i);
                }
            }
            shuffle($this->cardlist);
            $this->banker_play();
        }
    }

    function more(){
        // 再一張後就不能加注了，隱藏加注鈕
        $this->can_signal = FALSE;
        $card = array_pop($this->cardlist);
        array_push($this->player_cardlist, $card);
        if($this->sum($this->player_cardlist) > 21){
            $this->stop();
            return;
        }
    }

    function signal(){
        $this->bets = $this->bets << 1;
        $this->record(0, '玩家使用加注功能');
        $card = array_pop($this->cardlist);
        array_push($this->player_cardlist, $card);
        $this->stop();
    }

    function stop(){
        $win = 1;    // 1: 玩家贏 0: 平手 -1: 玩家輸
        if($this->sum($this->player_cardlist) > 21){
            $this->record($this->bets, '玩家爆牌');
            $this->alert = "你爆了 - $".$this->bets;
            $win = -1;
        }else{
            // banker play
            $this->banker_play();

            if($this->sum($this->banker_cardlist) > 21 ||
                $this->sum($this->player_cardlist) > $this->sum($this->banker_cardlist)){
                if($this->is_black_jack($this->player_cardlist)){
                    $this->bets *= 1.5;
                    $this->record($this->bets, '玩家 BLACKJACK (1.5 倍)');
                    $this->alert = "恭喜！BLACKJACK！ + $".$this->bets;
                }else if(count($this->player_cardlist) >= 5){
                    $this->bets *= 3;
                    $this->record($this->bets , '玩家 五龍 (3 倍)');
                    $this->alert = "恭喜！五龍！獎金三倍！ + $".$this->bets;
                }else if($this->sum($this->banker_cardlist) > 21){
                    $this->record($this->bets, '莊家爆牌');
                    $this->alert = "莊家爆了 + $".$this->bets;
                }else{
                    $this->record($this->bets, '玩家點數比莊家大');
                    $this->alert = "勝利！ + $".$this->bets;
                }
            }else if($this->sum($this->player_cardlist) < $this->sum($this->banker_cardlist)){
                $this->record(-$this->bets, '玩家點數比莊家小');
                if($this->is_black_jack($this->banker_cardlist)){
                    $this->alert = "QQ！莊家竟然 BLACKJACK - $".$this->bets;
                }else{
                    $this->alert = "QQ 輸了 - $".$this->bets;
                }
                $win = -1;
            }else if($this->sum($this->player_cardlist) == $this->sum($this->banker_cardlist)){
                $this->record(0, '本局平手');
                if($this->is_black_jack($this->player_cardlist)){
                    $this->bets *= 1.5;
                    $this->alert = "恭喜！BLACKJACK！但，莊家也 21 點 ^_^";
                }else if($this->is_black_jack($this->banker_cardlist)){
                    $this->alert = "雖然你 21 點！但莊家 BLACKJACK QQ".$this->bets;
                }else{
                    $this->alert = "平手";
                }
                $win = 0;
            }
        }

        // update current dollars
        if($win === 1){
            $this->banker_remainder -= $this->bets;
            $this->player_remainder += $this->bets;
        }else if($win === -1){
            $this->banker_remainder += $this->bets;
            $this->player_remainder -= $this->bets;
        }

        $db = new DB;
        $db->query("UPDATE `account` SET `remainder`=? WHERE id=?",
        $this->player_remainder, $this->user_id);

        $db->query("UPDATE `account` SET `remainder`=? WHERE id=?",
        $this->banker_remainder, "banker");
        $db->close();

        $this->is_stop = TRUE;
    }

    function is_black_jack($arr){
        if(count($arr) != 2) return false;
        return ($arr[0] % 13 === 0 && $arr[1] % 13 >= 9) ||
        ($arr[1] % 13 === 0 && $arr[0] % 13 >= 9);
    }

    function card_index_to_url($num){
        if($num == 52) return 'cards/BackCard.jpg';
        $url = '/cards/';
        switch($num % 13){
            case 0:  $url .= 'A'; break;
            case 10: $url .= 'J'; break;
            case 11: $url .= 'Q'; break;
            case 12: $url .= 'K'; break;
            default:
                $url .= ($num % 13 + 1); break;
        }
        switch(floor($num / 13)){
            case 0: $url .= 'C';   break;
            case 1: $url .= 'D';   break;
            case 2: $url .= 'H';   break;
            case 3: $url .= 'S';   break;
        }
        return $url.'.png';
    }

    function play(){
        // 洗牌
        $this->init_cardlist();
        // 設置
        array_push($this->banker_cardlist, array_pop($this->cardlist));
        array_push($this->banker_cardlist, array_pop($this->cardlist));
        array_push($this->player_cardlist, array_pop($this->cardlist));
        array_push($this->player_cardlist, array_pop($this->cardlist));
        $this->round_counter++;
        $this->record(0, "餘額 $this->player_remainder 元",
        "餘額 $this->banker_remainder 元");
        $this->record(0, "押注 $this->bets 元", "玩家押注 $this->bets 元");
    }

    function restart(){
        $this->can_signal = TRUE;
        $this->bets = ($this->bets > $this->player_remainder)? 10 : $this->last_bets;
        $this->is_stop = FALSE;
    }

    function record($dollar, $msg, $msg2=null){
        setcookie("round", $this->round_counter, time() + 3600);
        setcookie("earn", $dollar, time() + 3600);
        setcookie("detail", $msg, time() + 3600);
        setcookie("detail2", $msg2, time() + 3600);

        $db = new DB;
        if($msg2 === null) $msg2 = $msg;
        $db->query("INSERT INTO `record`(`id`, `round`, `earn`, `detail`, `detail2`) VALUES (?, ?, ?, ?, ?)", $this->user_id, $_COOKIE["round"], $_COOKIE["earn"], $_COOKIE["detail"], $_COOKIE["detail2"]);
        $db->close();
    }

    function render_record($view = self::PLAYER, $limit = 50){
        if($view == self::LATEST){
            echo isset($_COOKIE["round"])? "#".$_COOKIE["round"]." " : "";
            echo isset($_COOKIE["detail"])? $_COOKIE["detail"]." " : "";
            echo isset($_COOKIE["earn"])? $_COOKIE["earn"]."元" : "";
            return;
        }

        $db = new DB;
        if($view == self::PLAYER){
            $res = $db->query("SELECT `round`, `earn`, `detail`
                FROM `record` WHERE id=? ORDER BY serial DESC LIMIT ?",
                $this->user_id, $limit);
            while($row = $res->fetch_assoc()){
                $earn = $row["earn"];
                if($earn == 0){
                    $earn = "-";
                }else if($earn > 0){
                    $earn = "+".$earn;
                }

                echo '<tr>';
                echo '<td>#'.$row["round"].'</td>';
                echo '<td>'.$earn.'</td>';
                echo '<td>'.$row["detail"].'</td>';
                echo '</tr>';
            }
        }else if($view == self::BANKER){
            if($this->record_view === null){
                $res = $db->query("SELECT `id`, `round`, `earn`, `detail2`
                    FROM `record` ORDER BY serial DESC LIMIT ?",
                    $limit);
            }else{
                $res = $db->query("SELECT `id`, `round`, `earn`, `detail2`
                    FROM `record` WHERE id=? ORDER BY serial DESC LIMIT ?",
                    $this->record_view, $limit);
            }
            while($row = $res->fetch_assoc()){
                $earn = $row["earn"];
                $earn = -$earn;
                if($earn == 0){
                    $earn = "-";
                }else if($earn > 0){
                    $earn = "+".$earn;
                }

                echo '<tr>';
                echo '<td>@'.$row["id"].'</td>';
                echo '<td>#'.$row["round"].'</td>';
                echo '<td>'.$earn.'</td>';
                echo '<td>'.$row["detail2"].'</td>';
                echo '</tr>';
            }
        }

        $db->close();
    }
}
?>
<!DOCTYPE html>
<html style="height: 100%;">
<head>
    <title>ブラックジャック・無料ゲーム</title>
    <meta charset="utf-8">
    <link rel="stylesheet" type="text/css" href="main.css?<?php echo time();?>">
</head>
<body>
    <div class="wrapper">
<?php if(isset($_GET['main']) && $islogin):
    if(empty($_SESSION['stage'])){
        header('Location: /');
        exit();
    }

    $bj = $_SESSION['bj'];

    if($bj->player_remainder <= 0){
        header('Location: /?bankrupt');
    }

    if(isset($_GET['increase_bets'])){
        $bj->increase_bets($_GET['increase_bets']);
    }

    if(isset($_GET['start'])){
        if($bj->bets > $bj->player_remainder){
            header('Location: /?main');
        }
        $bj->play();
        $_SESSION['stage'] = "遊玩";
        header('Location: /?main');
    }

    if(isset($_GET['more'])){
        $bj->more();
        header('Location: /?main');
    }

    if(isset($_GET['signal'])){
        $bj->signal();
        header('Location: /?main');
    }

    if(isset($_GET['stop'])){
        $bj->stop();
        header('Location: /?main');
    }

    if(isset($_GET['toggle_cheat'])){
        $bj->toggle_cheat();
        header('Location: /?main');
    }

    if(isset($_GET['set_viewpoint'])){
        $bj->record_view = ($_POST['viewpoint'] === "")? null : $_POST['viewpoint'];
        header('Location: /?main');
    }
    // check if stop
    if($bj->is_stop){
        $_SESSION['stage'] = "回合計算";
    }
?>
    <div id="top" <?php if($bj->cheat) echo 'class="red"';?>>
        <a href="/" id="refresh" title="登出">ログアウト</a>
    <?php if($bj->cheat):?>
        <div id="cheat-mode-on">作弊模式已開啟</div>
    <?php endif;?>
        <div id="current-dollars" class="dollars"><?php
        echo $bj->player_remainder; ?></div>
    </div>
    <?php if($_SESSION['stage'] == "下注"):?>
        <div style="text-align: center;">
            <h1 title="下注">賭ける</h1>
            <p title="現在金額">現在の金額：<span class="dollars"><?php
            echo $bj->player_remainder; ?></span></p>
            <p title="下注金額">賭け金：<span class="dollars"><?php
            echo $bj->last_bets; ?></span></p>
            <div>
                <a href="/?main&increase_bets=10" class="button circle">+10</a>
                <a href="/?main&increase_bets=50" class="button circle">+50</a>
                <a href="/?main&increase_bets=100" class="button circle">+100</a>
            </div>
            <p><a href="/?main&increase_bets=-1" title="重置">リセット</a></p>
            <p><a href="/?main&start" class="button big" title="發牌！">始まる！</a></p>
        </div>
        <div id="bottom" <?php if($bj->cheat) echo 'class="red"';?>>
            <div id="bottom-2">
                <span class="bottom-text"><?php $bj->render_record($bj::LATEST);?></span>
            </div>
        </div>
    <?php elseif($_SESSION['stage'] == "遊玩" || $_SESSION['stage'] == "回合計算"):?>
        <div style="text-align: center;">
            <div class="card-list left">
                <?php
                    foreach($bj->banker_cardlist as $k => $v) {
                        if($k == count($bj->banker_cardlist) - 1 && !$bj->is_stop){
                            echo '<img src="'.$bj->card_index_to_url(52).'">';
                        }else{
                            echo '<img src="'.$bj->card_index_to_url($v).'">';
                        }
                    }
                ?>
            </div>
            <div class="card-list right">
                <?php
                    foreach($bj->player_cardlist as $v) {
                        echo '<img src="'.$bj->card_index_to_url($v).'">';
                    }
                ?>
            </div>
            <?php if($_SESSION['stage'] === "回合計算"):?>
                <div class="alert">
                    <div class="text"><?php echo $bj->alert?></div>
                    <a href="/?再來一局" id="restart-button">再來一局</a>
                </div>
            <?php endif;?>
        </div>
        <div id="bottom" <?php if($bj->cheat) echo 'class="red"';?>>
            <?php if($_SESSION['stage'] !== "回合計算"):?>
            <div id="bottom-2">
                <span class="bottom-text">押注：<span
                class="dollars"><?php echo $bj->bets;?></span></span>
                <?php if($bj->can_signal){
                    echo '<a href="/?main&signal" class="bottom-button">加注</a>';
                }
                ?>
                <a href="/?main&more" class="bottom-button">再一張</a>
                <a href="/?main&stop" class="bottom-button">不要了</a>
            </div>
            <?php endif;?>
        </div>
    <?php endif;?>
<?php elseif(isset($_GET['再來一局']) && $islogin):
    $bj = $_SESSION['bj'];
    $bj->restart();
    $_SESSION['stage'] = "下注";
    header('Location: /?main');
    exit();
?>
<?php elseif(isset($_GET['bankrupt'])):?>
    <h1>倒産</h1>
    <p>あなたは破産している</p>
    <p>您已破產，<a href="/?課金">立即儲值</a>已繼續遊戲</p>
    <p><a href="/">回主畫面</a></p>
<?php elseif(isset($_GET['課金'])):?>
    <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top" style="text-align: center;">
        <h1>課金</h1>
        <input type="hidden" name="cmd" value="_s-xclick">
        <input type="hidden" name="hosted_button_id" value="E6SV78CVAH8JY">
        <table style="margin: 0px auto;">
        <tr><td><input type="hidden" name="on0" value="幣值">幣值</td></tr><tr><td><select name="os0">
        	<option value="$1000 (10% off)">$1000 (10% off) NT$900 TWD</option>
        	<option value="$500 (5% off)">$500 (5% off) NT$475 TWD</option>
        	<option value="$100">$100 NT$100 TWD</option>
        </select> </td></tr>
        </table>
        <input type="hidden" name="currency_code" value="TWD">
        <input type="image" src="https://www.paypalobjects.com/zh_TW/TW/i/btn/btn_paynowCC_LG.gif" border="0" name="submit"
        alt="PayPal － 更安全、更簡單的線上付款方式！">
        <img alt="" border="0" src="https://www.paypalobjects.com/zh_TW/i/scr/pixel.gif" width="1" height="1">
    </form>
<?php elseif(isset($_GET['reg'])):?>
    <form action="/?action_reg" method="post">
        <input class="input" type="text" name="i" placeholder="ID">
        <input class="input" title="輸入密碼" type="password" name="p"
        placeholder="パスワード">
        <input class="input" title="再次輸入密碼" type="password" name="q"
        placeholder="もう一度パスワードを入力してください">
        <input class="input submit" title="申請帳號" type="submit"
        value="アカウントを作成する">
    </form>
<?php
elseif(isset($_GET['action_reg'])):
    if($_POST['i'] === ""){
        die('id は空であってはなりません<br>id must not be empty<br><a title="回主畫面" href="/">ホーム</a>');
    }
    if($_POST['p'] === ""){
        die('パスワードは空であってはならない<br>password must not be empty<br><a title="回主畫面" href="/">ホーム</a>');
    }
    if($_POST['p'] !== $_POST['q']){
        die('パスワードとパスワードが一致しないことを確認<br>Password and Confirm password does not match<br><a title="回主畫面" href="/">ホーム</a>');
    }
    $db = new DB();
    $res = $db->query("SELECT COUNT(id) as num
    FROM `account` WHERE id=?", $_POST['i']);
    if($res->fetch_assoc()['num'] > 0){
        die('IDが重複しています<br>The ID has been duplicated<br><a title="回主畫面" href="/">ホーム</a>');
    }

    if($db->query("INSERT INTO `account`(`id`, `pwd`) VALUES (? , ?)",
    $_POST['i'], hash("sha512", $_POST['p'])) === TRUE){
        header('Location: /');
    }else{
        die('Database error<br><a title="回主畫面" href="/">ホーム</a>');
    }
    exit();
elseif(isset($_GET['action_login'])):
    setcookie("round", "", time() - 3600);
    setcookie("earn", "", time() - 3600);
    setcookie("detail", "", time() - 3600);
    setcookie("detail2", "", time() - 3600);

    if($_POST['i'] === ""){
        die('id は空であってはなりません<br>id must not be empty<br><a title="回主畫面" href="/">ホーム</a>');
    }
    if($_POST['p'] === ""){
        die('パスワードは空であってはならない<br>password must not be empty<br><a title="回主畫面" href="/">ホーム</a>');
    }
    $db = new DB();
    $res = $db->query("SELECT COUNT(id) as num FROM `account` WHERE id=? and pwd=?",
    $_POST['i'], hash("sha512", $_POST['p']));

    if($res !== FALSE){
        if($res->fetch_assoc()['num'] == 1){
            $_SESSION['login'] = $_POST['i'];
            $_SESSION['stage'] = "下注";
            $_SESSION['bj'] = new BlackJack($_SESSION['login']);
            header('Location: /?main');
        }else{
            die('IDやパスワードがエラーになっている<br>ID or password are error<br><a title="回主畫面" href="/">ホーム</a>');
        }
    }else{
        die('Database error<br><a title="回主畫面" href="/">ホーム</a>');
    }
    exit();
else:
?>
    <?php session_destroy();?>
    <div style="text-align: center;">
        <h1 title="21點">ブラックジャック</h1>
        <h2 title="登入">ログイン</h2>
        <form action="/?action_login" method="post">
            <input class="input" type="text" name="i" placeholder="ID">
            <input class="input" type="password" name="p" placeholder="password">
            <input class="input submit" title="login" type="submit" value="ログイン">
            <p><a href="/?reg" title="註冊新用戶">新規アカウント登録</a></p>
            <ul style="text-align: left; max-width: 450px; margin:10px auto; font-size: .88em; color: #757575;">
                <li>開始遊戲後，左側有「莊家寶典」可開啟莊家作弊功能及查看記錄</li>
                <li>右側有「玩家寶典」也可以查看記錄</li>
                <li>莊家預設有 100 萬元</li>
                <li>日文文字滑鼠停留附有中文提示。</li>
            </ul>
        </form>
    </div>
<?php endif;?>
    </div><!--class=wrapper-->
<?php if(isset($_GET['main']) && $islogin):?>
    <div class="side left">
        <p class="bigger">莊家寶典</p>
        <a href="/?main&toggle_cheat"
        class="input submit center"><?php echo ($bj->cheat)? "關閉":"開啟"?>莊家必勝模式</a>
        <form action="/?main&set_viewpoint" method="post">
            <select class="input" name="viewpoint">
                <?php
                    echo '<option value="">全部玩家</option>';
                    $db = new DB;
                    $res = $db->query("SELECT id FROM account WHERE id <> ?", "banker");
                    while($row = $res->fetch_assoc()){
                        echo '<option value="'.htmlentities($row['id']).'"';
                        if($bj->record_view === $row['id']){
                            echo ' selected';
                        }
                        echo ">{$row['id']}</option>";
                    }
                    $db->close();
                ?>
            </select>
            <input class="input submit" type=submit value="查看">
        </form>
        <p>查看<?php echo ($bj->record_view === null)?
        "所有" :  "@".$bj->record_view?>玩家記錄表</p>
        <p class="smaller">莊家視角 近 50 筆記錄</p>
        <table>
            <thead>
                <tr>
                    <th>玩家 ID</th>
                    <th>局數</th>
                    <th>賠 / 賺</th>
                    <th>備註</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    $bj->render_record($bj::BANKER);
                ?>
            </tbody>
        </table>
    </div>
    <div class="side">
        <p class="bigger">玩家寶典</p>
        <p class="bigger">賭博記錄表</p>
        <p class="smaller">近 50 筆記錄</p>
        <table>
            <thead>
                <tr>
                    <th>局數</th>
                    <th>賠 / 賺</th>
                    <th>備註</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    $bj->render_record($bj::PLAYER);
                ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
</body>
</html>
