<?php
// ini_set('display_errors', "On");
// ini_set( 'display_errors', 1 );

require_once("./PHPMailer/src/Exception.php");
require_once("./PHPMailer/src/PHPMailer.php");
require_once("./PHPMailer/src/SMTP.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
// [A] HELPER FUNCTIONS
//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
//配列連結の処理
function _Connect2Val($arr)
{
    $out = '';
    foreach ($arr as $key => $val) {
        if ($key === 0 || $val == '') {//配列が未記入（0）、または内容が空のの場合には連結文字を付加しない（型まで調べる必要あり）
            $key = '';
        } elseif (strpos($key, "円") !== false && $val != '' && preg_match("/^[0-9]+$/", $val)) {
            $val = number_format($val);//金額の場合には3桁ごとにカンマを追加
        }
        $out .= $val . $key;
    }
    return $out;
}

//全角→半角変換
function _Zenkaku2Hankaku($key, $out, $hankaku_array)
{
    global $encode;
    if (is_array($hankaku_array) && function_exists('mb_convert_kana')) {
        foreach ($hankaku_array as $hankaku_array_val) {
            if ($key == $hankaku_array_val) {
                $out = mb_convert_kana($out, 'a', $encode);
            }
        }
    }
    return $out;
}

//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
// [B] VALIDATIONS
//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
//@Brief : remove special vhars
function h($string)
{
    global $encode;
    return htmlspecialchars($string, ENT_QUOTES, $encode);
}

//@Brief : ファイル名の取得
function _GetFileName($path,$index)
{
    $name = pathinfo( $path, PATHINFO_FILENAME); 
    $ext  = pathinfo( $path, PATHINFO_EXTENSION); 

    $file = $name . '_' . $index . '.' . $ext;

    return $file;
}

//@Brief : ファイルを添付する
function _UploadImgFile($title,$tmpName,$fileName,$fileSize)
{
    $uploadDir      = "./temp/";
    $filesizeMax    = 1024*1024*4;

    if (is_uploaded_file($tmpName)) {

        if( $fileSize > $filesizeMax ){
            return "<p class=\"error_messe\">" . $title . "画像のファイルサイズが4MBを超えています。</p>\n";
        }

        if ( move_uploaded_file($tmpName , $uploadDir.$fileName )) {
            //echo $fileName . "をアップロードしました。";
            return '';
        } else {
            //echo "ファイルをアップロードできません。";
            return "<p class=\"error_messe\">" . $title . "画像のアップロードに失敗しました。</p>\n";
        }

    }
    //echo "ファイルが選択されていません。";
    return "<p class=\"error_messe\">" . $title . "画像が選択されていません。</p>\n";
}

//@Brief : メールが正しい形式化をチェック
function _CheckMail($str)
{
    $mailaddress_array = explode('@', $str);
    if (preg_match("/^[\.!#%&\-_0-9a-zA-Z\?\/\+]+\@[!#%&\-_0-9a-zA-Z]+(\.[!#%&\-_0-9a-zA-Z]+)+$/", "$str") && count($mailaddress_array) ==2) {
        return true;
    } else {
        return false;
    }
}

//@Brief : バリデーションのコア処理 (<form>のinput用)
function FormValidation($require)
{
    $res['errm'] = '';
    $res['empty_flag'] = 0;

    $img_id = 1;
    foreach ($require as $requireKey => $requireVal) 
    {
        //通常のinputデータ
        $pDatas = $_POST["data"];
        foreach ($pDatas as $key => $val) {

            if ($requireKey == "data[".$key."]") 
            {
                if ($val == '') {
                    $res['errm'] .= "<p class=\"error_messe\">【".h($requireVal)."】は必須項目です。</p>\n";
                    $res['empty_flag'] = 1;
                }
                break;
            }

        }

        //画像・ファイル系
        $pFiles = $_FILES["file"];
        foreach ($pFiles as $fkey => $fval) 
        {
            if ($requireKey == "file[".$fkey."]") 
            {
                $img_tmpName = $fval['tmp_name'];

                $img_name   = _GetFileName($fval['name'], $img_id);
                $img_size  = $fval['size'];

                $err = _UploadImgFile("【".h($requireVal)."】",$img_tmpName,$img_name,$img_size);
                if($err !='')
                {
                    $res['errm'] .= $err;
                    $res['empty_flag'] = 1;
                }
                $img_id ++;
                break;
            }
        }
    }

    return $res;
}

//@Briev : バリデーションのコア処理
function ValidationCheck($requireArr, $inputEmail)
{
    $ret['errm']        = '';
    $ret['empty_flag']  = 0;
    $ret['user_mail']  = '';
    $ret['sendmail']    = 0;

    //----------------------------------------------------------------
    //mail_set -> 確認画面：validationがOK状態で、送信しますか？
    //----------------------------------------------------------------
    if (isset($_POST['mail_set'])) 
    {
        $ret['sendmail']   = 1;

        $sDataId           = intval( preg_replace('/[^0-9]/', '', $inputEmail) );
        $ret['user_mail']  = $_POST['data'][$sDataId];
    }
    
    //----------------------------------------------------------------
    //indexからformを送ったら、最初はここになる！
    //----------------------------------------------------------------
    else
    {
        // input バリデーション
        {
            $retform  = FormValidation($requireArr);//必須チェック実行し返り値を受け取る
            $ret['errm']        = $retform['errm'];
            $ret['empty_flag']  = $retform['empty_flag'];
        }

        //メールアドレスチェック
        if (empty($ret['errm'])) 
        {
            foreach ($_POST['data'] as $key=>$val) 
            {
                if ('data['.$key.']' == $inputEmail) 
                {
                    if (_CheckMail($val)) 
                    {
                        $ret['user_mail'] = h($val);
                    }
                    else
                    {
                        $ret['errm']        .= "<p class=\"error_messe\">メールアドレスの形式が正しくありません。</p>\n";
                        $ret['empty_flag']  = 1;
                    }                
                }
            }
        }
    }
    //----------------------------------------------------------------

    return $ret;
}

//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
// [C] CORE MAIL SEND FUNCTION
//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
//@Brief : 送信メールにPOSTデータをセットする関数
function PostData2Mail($master, $post)
{
    global $hankaku,$hankaku_array;
    $resArray = '';

    //input data -----------------------------------------
    foreach ($post['data'] as $keyraw => $val) 
    {
        $key = 'data['.$keyraw.']';
        $out = '';
        if (is_array($val)) {
            foreach ($val as $key02 => $item) {
                //連結項目の処理
                if (is_array($item)) {
                    $out .= _Connect2Val($item);
                } else {
                    $out .= $item . ', ';
                }
            }
            $out = rtrim($out, ', ');
        } else {
            $out = $val;
        }//チェックボックス（配列）追記ここまで
        
        if (version_compare(PHP_VERSION, '5.1.0', '<=')) {//PHP5.1.0以下の場合のみ実行（7.4でget_magic_quotes_gpcが非推奨になったため）
            if (get_magic_quotes_gpc()) {
                $out = stripslashes($out);
            }
        }
        
        //全角→半角変換
        if ($hankaku == 1) {
            $out = _Zenkaku2Hankaku($key, $out, $hankaku_array);
        }
        $resArray .= "【 ".h($master[$key])." 】 ".h($out)."\n";
    }

    //files -----------------------------------------
    foreach ($post['file'] as $keyraw => $val) 
    {
        $key = 'file['.$keyraw.']';
        $out = basename($val);
        $resArray .= "【 ".h($master[$key])." 】 ".h($out)."\n";
    }

    return $resArray;
}


//@Brief : メール送信の処理本体
function SendMail ($to, $subject, $body, $customheader, $senderadmin)
{
    $mailer = new PHPMailer(true);
    try {
        //Server settings
        $mailer->CharSet = 'UTF-8';
        $mailer->SMTPDebug = 0;
        // $mailer->SMTPDebug = SMTP::DEBUG_SERVER;
        $mailer->isSMTP();
        $mailer->Host       = $senderadmin['smtp'];
        $mailer->SMTPAuth   = true;
        $mailer->Username   = $senderadmin['mailaddr'];
        $mailer->Password   = $senderadmin['mailpass'];

        //-------------------------------------
        // AWS
        if ($senderadmin['aws'])
        {
            $mailer->Port = 465; // ※ セキュリティグループのアウトバウンドでポートが通っているか要確認！！
            $mailer->SMTPSecure = 'ssl';
        }
        //ロリポップ
        else
        {
            $mailer->Port = 587;
            $mailer->SMTPSecure = 'CRAM-MD5';
        }
        //-------------------------------------
        /*
        $mailer->SMTPOptions = array(
            'ssl' => array(
                'verify_peer'       => false,	//SSLサーバー証明書の検証を要求するか（デフォルト：true）
                'verify_peer_name'  => false,	//ピア名の検証を要求するか（デフォルト：true）
                'allow_self_signed' => true		//自己証明の証明書を許可するか（デフォルト：false、trueにする場合は「verify_peer」をfalseに）
            )
        );
        */
        //-------------------------------------
        //Sender (fixed)
        $mailer->setFrom($senderadmin['mailaddr'], mb_encode_mimeheader('ビジマチ事務局'));
        
        //送信先が複数設定されていた場合の対応
        $to = explode(',',$to);
        for ($i = 0; $i < count($to); $i++) {
            $mailer->addAddress($to[$i]);
        }

        //Custom header 
        //$mailer->addCustomHeader('X-custom-header', $customheader);

        //Content
        $mailer->Subject = $subject;
        $mailer->Body = $body;

        //添付ファイル
        foreach($_POST['file'] as $key=>$val)
        {
            $mailer->addAttachment($val);
        }

        $mailer->send();
    } 
    catch (Exception $e) 
    {
        echo '<div class="text-center" style="color:red;">メール送信失敗しました<br>エラー: ', $mailer->ErrorInfo . '</div>';
    }


}

//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
// [D] VALIDATION OK -> 確認画面用
//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
//確認画面の入力内容出力用関数
function confirmOutput($master, $inputs, $files)
{
    global $hankaku,$hankaku_array,$useToken,$confirmDsp,$replaceStr;
    $html = '';
    $item11_tmp = '';
    $item12_tmp = '';

    //input data[] ----------------------------------
    foreach ($inputs['data'] as $keyraw => $val) 
    {
        $key = 'data['.$keyraw.']';
        $out = '';
        if (is_array($val)) {
            foreach ($val as $key02 => $item) {
                //連結項目の処理
                if (is_array($item)) {
                    $out .= _Connect2Val($item);
                } else {
                    $out .= $item . ', ';
                }
            }
            $out = rtrim($out, ', ');
        } else {
            $out = $val;
        }//チェックボックス（配列）追記ここまで
        
        //PHP5.1.0以下の場合のみ実行（7.4でget_magic_quotes_gpcが非推奨になったため）
        if (version_compare(PHP_VERSION, '5.1.0', '<=')) 
        {
            if (get_magic_quotes_gpc()) {
                $out = stripslashes($out);
            }
        }
        
        $out = nl2br(h($out));//※追記 改行コードを<br>タグに変換
        $key = h($key);
        $out = str_replace($replaceStr['before'], $replaceStr['after'], $out);//機種依存文字の置換処理
        
        //全角→半角変換
        if ($hankaku == 1) {
            $out = _Zenkaku2Hankaku($key, $out, $hankaku_array);
        }

        //単位
        $unit = 'unit['.$keyraw.']';
        foreach($master as $keyunit => $unitval)
        {
            if ($keyunit == $unit)
            {
                $out .= ' '.$unitval;
                break;
            }
        }
        
        $html .= "<tr><th>".$master[$key]."</th><td>".$out;
        $html .= '<input type="hidden" name="'.$key.'" value="'.str_replace(array("<br />","<br>"), "", $out).'" />';
        $html .= "</td></tr>\n";
    }

    //file[] ----------------------------------
    $uploadDir  = "./temp/";
    $imgId      = 1;
    foreach ($inputs['file'] as $keyraw => $val) 
    {
        $key = 'file['.$keyraw.']';

        if($val['tmp_name']!='')
        {
            $imgPath = $uploadDir . _GetFileName($val['name'], $imgId); 
            $html .= "<tr><th>". $master[$key] ."</th><td>";
            $html .=  '<img src="' . $imgPath . '" style="width:200px; object-fit: cover;">';
            $html .= "</td></tr>\n";
            $html .= '<input type="hidden" name="'.$key.'" value="' . $imgPath . '" />';

            $imgId++;
        }
    }
    
    // トークンをセット ----------------------------------
    if ($useToken == 1 && $confirmDsp == 1) {
        $token = sha1(uniqid(mt_rand(), true));
        $_SESSION['mailform_token'] = $token;
        $html .= '<input type="hidden" name="mailform_token" value="'.$token.'" />';
    }
    
    return $html;
}

//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
// [E] MAIL BODY
//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
//@Brief : 管理者宛送信メールヘッダ
function MailHeaderAdmin($userMail, $post_mail, $BccMail, $to)
{
    $header = '';
    if ($userMail == 1 && !empty($post_mail)) {
        $header="From: $post_mail\n";
        if ($BccMail != '') {
            $header.="Bcc: $BccMail\n";
        }
        $header.="Reply-To: ".$post_mail."\n";
    } else {
        if ($BccMail != '') {
            $header="Bcc: $BccMail\n";
        }
        $header.="Reply-To: ".$to."\n";
    }
    $header.="Content-Type:text/plain;charset=iso-2022-jp\nX-Mailer: PHP/".phpversion();
    return $header;
}

//@Brief : 管理者宛送信メールボディ
function MailBodyAdmin($master, $arr, $subject, $mailFooterDsp, $mailSignature, $encode, $confirmDsp)
{
    $adminBody="「".$subject."」からメールが届きました\n\n";
    $adminBody .="＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝\n\n";
    $adminBody.= PostData2Mail($master, $arr);//POSTデータを関数からセット
    $adminBody.="\n＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝\n";
    $adminBody.="送信された日時：".date("Y/m/d (D) H:i:s", time())."\n";
    $adminBody.="送信者のIPアドレス：".@$_SERVER["REMOTE_ADDR"]."\n";
    $adminBody.="送信者のホスト名：".getHostByAddr(getenv('REMOTE_ADDR'))."\n";
    if ($confirmDsp != 1) {
        $adminBody.="問い合わせのページURL：".@$_SERVER['HTTP_REFERER']."\n";
    } else {
        $adminBody.="問い合わせのページURL：".@$arr['httpReferer']."\n";
    }
    if ($mailFooterDsp == 1) {
        $adminBody.= $mailSignature;
    }
//    return mb_convert_encoding($adminBody, "JIS", $encode);
    return $adminBody;
}

//@Brief : ユーザ宛送信メールヘッダ
function MailHeaderUser($refrom_name, $to, $encode)
{
    $reheader = "From: ";
    if (!empty($refrom_name)) {
        $default_internal_encode = mb_internal_encoding();
        if ($default_internal_encode != $encode) {
            mb_internal_encoding($encode);
        }
        $reheader .= mb_encode_mimeheader($refrom_name)." <".$to.">\nReply-To: ".$to;
    } else {
        $reheader .= "$to\nReply-To: ".$to;
    }
    $reheader .= "\nContent-Type: text/plain;charset=iso-2022-jp\nX-Mailer: PHP/".phpversion();
    return $reheader;
}

//@Brief : ユーザ宛送信メールボディ
function MailBodyUser($master, $arr, $dsp_name, $remail_text, $mailFooterDsp, $mailSignature, $encode)
{
    $userBody = '';
    if (isset($arr[$dsp_name])) {
        $userBody = h($arr[$dsp_name]). " 様\n";
    }
    $userBody.= $remail_text;
    $userBody.="\n＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝\n\n";
    $userBody.= PostData2Mail($master, $arr);//POSTデータを関数からセット
    $userBody.="\n＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝\n\n";
    $userBody.="送信日時：".date("Y/m/d (D) H:i:s", time())."\n";
    if ($mailFooterDsp == 1) {
        $userBody.= $mailSignature;
    }
    // return mb_convert_encoding($userBody, "JIS", $encode);
    return $userBody;
}


//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
// [F] INITIALIZE FUNCTIONS
//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
//@Brief : 配列を綺麗にする
function sanitize($arr)
{
    if (is_array($arr)) {
        return array_map('sanitize', $arr);
    }
    return str_replace("\0", "", $arr);
}
//Shift-JISの場合に誤変換文字の置換関数
function sjisReplace($arr, $encode)
{
    foreach ($arr as $key => $val) {
        $key = str_replace('＼', 'ー', $key);
        $resArray[$key] = $val;
    }
    return $resArray;
}
//リファラチェック
function refererCheck($Referer_check, $Referer_check_domain)
{
    if ($Referer_check == 1 && !empty($Referer_check_domain)) {
        if (strpos($_SERVER['HTTP_REFERER'], $Referer_check_domain) === false) {
            return exit('<p align="center">リファラチェックエラー。フォームページのドメインとこのファイルのドメインが一致しません</p>');
        }
    }
}
?>


<?php 
//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
// CORE CONTENTS
//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
header("Content-Type:text/html;charset=utf-8"); 

//error_reporting(E_ALL | E_STRICT);
##-----------------------------------------------------------------------------------------------------------------##
#  PHPメールプログラム　フリー版 最終更新日2018/07/27
#　改造や改変は自己責任で行ってください。
#  ref: http://www.php-factory.net/
##-----------------------------------------------------------------------------------------------------------------##
if (version_compare(PHP_VERSION, '5.1.0', '>=')) {//PHP5.1.0以上の場合のみタイムゾーンを定義
    date_default_timezone_set('Asia/Tokyo');//タイムゾーンの設定（日本以外の場合には適宜設定ください）
}


//================================================================================
// CONST (編集必要なし！)
//================================================================================
//スパム防止のためのリファラチェック（フォーム側とこのファイルが同一ドメインであるかどうかのチェック）(する=1, しない=0)
//※有効にするにはこのファイルとフォームのページが同一ドメイン内にある必要があります
$Referer_check = 0;

//リファラチェックを「する」場合のドメイン ※設置するサイトのドメインを指定して下さい。
//もしこの設定が間違っている場合は送信テストですぐに気付けます。
$Referer_check_domain = "php-factory.net";

/*セッションによるワンタイムトークン（CSRF対策、及びスパム防止）(する=1, しない=0)
※ただし、この機能を使う場合は↓の送信確認画面の表示が必須です。（デフォルトではON（1）になっています）
※【重要】ガラケーは機種によってはクッキーが使えないためガラケーの利用も想定してる場合は「0」（OFF）にして下さい（PC、スマホは問題ないです）*/
$useToken = 1;

//---------------------------
// 管理者宛のメールで差出人を送信者のメールアドレスにする(する=1, しない=0)
// する場合は、メール入力欄のname属性の値を「$Email」で指定した値にしてください。
//メーラーなどで返信する場合に便利なので「する」がおすすめです。
$userMail = 1;

// Bccで送るメールアドレス(複数指定する場合は「,」で区切ってください 例 $BccMail = "aa@aa.aa,bb@bb.bb";)
$BccMail = "";

//---------------------------

// 送信確認画面の表示(する=1, しない=0)
$confirmDsp = 1;

// 送信完了後に自動的に指定のページ(サンクスページなど)に移動する(する=1, しない=0)
// CV率を解析したい場合などはサンクスページを別途用意し、URLをこの下の項目で指定してください。
// 0にすると、デフォルトの送信完了画面が表示されます。
$jumpPage = 0;

// 送信完了後に表示するページURL（上記で1を設定した場合のみ）※httpから始まるURLで指定ください。（相対パスでも基本的には問題ないです）
//$thanksPage = "http://xxx.xxxxxxxxx/thanks.html";

// 必須入力項目を設定する(する=1, しない=0)
$requireCheck = 1;

//================================================================================
// [※] [SETTINGS] 必須設定 !!!!
//================================================================================

//>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
//　[※] 管理者宛のメール設定
//>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
//サイトのトップページのURL　※デフォルトでは送信完了後に「トップページへ戻る」ボタンが表示されますので
$site_top = "https://lpbiz002s.caelsolus.xyz/";


//管理者の情報（返信メールのsender + 問い合わせの送信先　に使用する！）
$adminAcc =
[
    //aws flag 
    'aws'       => false,   

    //Lollipop
    'mailaddr'  => 'okitegami@tukuru-co.com',
    'smtp'      => 'smtp.lolipop.jp',
    'mailpass'  => 'j_tgh-Rp4kBvTaR6',
    
    // 丸岡用
    // 'mailaddr'  => 'nobutakku@gmail.com',
    // 'smtp'      => 'smtp.gmail.com',
    // 'mailpass'  => '20210421Henk0u@',

    //AWS
    //'mailaddr'  => 'support@tucoure.com',
    //'smtp'      => 'smtp.mail.us-east-1.awsapps.com',
    //'mailpass'  => 'EZ8922y-2wsT',
];


// 管理者宛に送信されるメールのタイトル（件名）
$subject = "【問い合わせ】ビジマチの問い合わせ";

//>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// [※] Validation 系
//>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
$InputEmail = "data[4]";    //フォームのメールアドレス入力箇所のname属性の値（name="○○"　の○○部分）

$require = [
    //'data[0]' => '貴社名',
    'data[1]' => '氏名',
    'data[2]' => '住所',
    'data[3]' => '電話番号',
    'data[4]' => 'メールアドレス',
    'data[5]' => 'お問い合わせ詳細',

    //画像・ファイル系
    //'file[0]' => '身分証明書',
];

//>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// [※] マスターデータ 
//>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
$mst_datas = [
    'data[0]' => '貴社名',
    'data[1]' => '氏名',
    'data[2]' => '住所',
    'data[3]' => '電話番号',
    'data[4]' => 'メールアドレス',
    'data[5]' => 'お問い合わせ詳細',

    //画像・ファイル系
    //'file[0]' => '身分証明書',

    //単位（なければ、書く必要なし！）
    //'unit[3]' => 'cm',
];


//================================================================================
//  [※][SETTINGS] 自動返信メール設定(START)
//================================================================================

// 差出人に送信内容確認メール（自動返信メール）を送る(送る=1, 送らない=0)
// 送る場合は、フォーム側のメール入力欄のname属性の値が上記「$Email」で指定した値と同じである必要があります
$remail = 1;

//自動返信メールの送信者欄に表示される名前　※あなたの名前や会社名など（もし自動返信メールの送信者名が文字化けする場合ここは空にしてください）
$refrom_name = "ビジマチ事務局";

// 差出人に送信確認メールを送る場合のメールのタイトル（上記で1を設定した場合のみ）
$re_subject = "お問い合わせありがとうございました";

//フォーム側の「名前」箇所のname属性の値　※自動返信メールの「○○様」の表示で使用します。
//指定しない、または存在しない場合は、○○様と表示されないだけです。あえて無効にしてもOK
// $dsp_name = 'お客';

//自動返信メールの冒頭の文言 ※日本語部分のみ変更可
$remail_text = <<< TEXT

この度はお問い合わせメールをお送りいただきありがとうございます。
後ほど、担当者よりご連絡をさせていただきます。
今しばらくお待ちくださいますようよろしくお願い申し上げます。
 
ビジマチ事務局



送信内容は以下になります。

TEXT;


//自動返信メールに署名（フッター）を表示(する=1, しない=0)※管理者宛にも表示されます。
$mailFooterDsp = 0;

//上記で「1」を選択時に表示する署名（フッター）（FOOTER～FOOTER;の間に記述してください）
$mailSignature = <<< FOOTER

──────────────────────
TUKURU株式会社
──────────────────────
〒104-0032
東京都中央区八丁堀3-11-8 美和ビル8階（本社）
電話番号: 03-6222-9582
         03-6222-9583（FAX)
──────────────────────

FOOTER;


//================================================================================
//  [SETTINGS] 自動返信メール設定(END)
//================================================================================

//メールアドレスの形式チェックを行うかどうか。(する=1, しない=0)
//※デフォルトは「する」。特に理由がなければ変更しないで下さい。メール入力欄のname属性の値が上記「$Email」で指定した値である必要があります。
$mail_check = 1;

//全角英数字→半角変換を行うかどうか。(する=1, しない=0)
$hankaku = 0;

//全角英数字→半角変換を行う項目のname属性の値（name="○○"の「○○」部分）
//※複数の場合にはカンマで区切って下さい。（上記で「1」を指定した場合のみ有効）
//配列の形「name="○○[]"」の場合には必ず後ろの[]を取ったものを指定して下さい。
$hankaku_array = array('電話番号','金額');

//-fオプションによるエンベロープFrom（Return-Path）の設定(する=1, しない=0)　
//※宛先不明（間違いなどで存在しないアドレス）の場合に 管理者宛に「Mail Delivery System」から「Undelivered Mail Returned to Sender」というメールが届きます。
//サーバーによっては稀にこの設定が必須の場合もあります。
//設置サーバーでPHPがセーフモードで動作している場合は使用できませんので送信時にエラーが出たりメールが届かない場合は「0」（OFF）として下さい。
$use_envelope = 0;

//機種依存文字の変換
/*たとえば㈱（かっこ株）や①（丸1）、その他特殊な記号や特殊な漢字などは変換できずに「？」と表示されます。それを回避するための機能です。
確認画面表示時に置換処理されます。「変換前の文字」が「変換後の文字」に変換され、送信メール内でも変換された状態で送信されます。（たとえば「㈱」の場合、「（株）」に変換されます）
必要に応じて自由に追加して下さい。ただし、変換前の文字と変換後の文字の順番と数は必ず合わせる必要がありますのでご注意下さい。*/

//変換前の文字
$replaceStr['before'] = array('①','②','③','④','⑤','⑥','⑦','⑧','⑨','⑩','№','㈲','㈱','髙');
//変換後の文字
$replaceStr['after'] = array('(1)','(2)','(3)','(4)','(5)','(6)','(7)','(8)','(9)','(10)','No.','（有）','（株）','高');



//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// 0) INITIALIZATION
//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
//トークンチェック用のセッションスタート
if ($useToken == 1 && $confirmDsp == 1) {
    session_name('PHPMAILFORMSYSTEM');
    session_start();
}
$encode = "UTF-8";//このファイルの文字コード定義（変更不可）
if (isset($_GET)) {
    $_GET = sanitize($_GET);
}//NULLバイト除去//
if (isset($_POST)) {
    $_POST = sanitize($_POST);
}//NULLバイト除去//
if (isset($_COOKIE)) {
    $_COOKIE = sanitize($_COOKIE);
}//NULLバイト除去//
if ($encode == 'SJIS') {
    $_POST = sjisReplace($_POST, $encode);
}//Shift-JISの場合に誤変換文字の置換実行
$funcRefererCheck = refererCheck($Referer_check, $Referer_check_domain);//リファラチェック実行

//変数初期化
$sendmail = 0;
$empty_flag = 0;
$user_mail = '';
$errm ='';
$header ='';


//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
//1) バリデーションチェック
//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
$ret1       = ValidationCheck( $require, $InputEmail );
{
    $sendmail   = $ret1['sendmail'];
    $user_mail  = $ret1['user_mail'];
    
    $errm       = $ret1['errm'];
    $empty_flag = $ret1['empty_flag'];
}

//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
//2.1) メールを送信する！
//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
if (($confirmDsp == 0 || $sendmail == 1) && 
    $empty_flag != 1) 
{
    //トークンチェック（CSRF対策）※確認画面がONの場合のみ実施
    if ($useToken == 1 && $confirmDsp == 1) {
        if (empty($_SESSION['mailform_token']) || ($_SESSION['mailform_token'] !== $_POST['mailform_token'])) {
            exit('ページ遷移が不正です');
        }
        if (isset($_SESSION['mailform_token'])) {
            unset($_SESSION['mailform_token']);
        }//トークン破棄
        if (isset($_POST['mailform_token'])) {
            unset($_POST['mailform_token']);
        }//トークン破棄
    }
    
    //差出人に届くメールをセット
    if ($remail == 1) {
        $userBody = MailBodyUser($mst_datas, $_POST, $dsp_name, $remail_text, $mailFooterDsp, $mailSignature, $encode);
        $reheader = MailHeaderUser($refrom_name, $adminAcc['mailaddr'], $encode);
        $re_subject = "=?iso-2022-jp?B?".base64_encode(mb_convert_encoding($re_subject, "JIS", $encode))."?=";
    }
    //管理者宛に届くメールをセット
    $adminBody = MailBodyAdmin($mst_datas, $_POST, $subject, $mailFooterDsp, $mailSignature, $encode, $confirmDsp);
    $header = MailHeaderAdmin($userMail, $user_mail, $BccMail, $adminAcc['mailaddr']);
    $subject = "=?iso-2022-jp?B?".base64_encode(mb_convert_encoding($subject, "JIS", $encode))."?=";

    //PHPMailerの使用 (`true` enables exceptions)
    $mailer = new PHPMailer(true);

    //-fオプションによるエンベロープFrom（Return-Path）の設定(safe_modeがOFFの場合かつ上記設定がONの場合のみ実施)
    if ($use_envelope == 0) {
        SendMail($adminAcc['mailaddr'], $subject, $adminBody, $header, $adminAcc);
        if ($remail == 1 && !empty($user_mail)) {
            SendMail($user_mail, $re_subject, $userBody, $reheader, $adminAcc);
        }
    } else {
        if ($remail == 1 && !empty($user_mail)) {
            SendMail($user_mail, $re_subject, $userBody, $reheader, $adminAcc);
        }
    }

    // 添付ファイル削除
    unlink($_POST['bustup']);
    unlink($_POST['body']);

} 

//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
//2.2) 確認画面
//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
elseif ($confirmDsp == 1) 
{
?>
    <!DOCTYPE HTML>
    <html lang="ja">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
    <meta name="format-detection" content="telephone=no">
    <title>確認画面</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css" integrity="sha384-TX8t27EcRE3e/ihU7zmQxVncDAy5uIKz4rEkgIXeMed4M0jlfIDPvg6uqKI2xXr2" crossorigin="anonymous">
    <link rel="stylesheet" href="css/stylesheet.css" />
    <link rel="stylesheet" href="css/stylemail.css" />

    <script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
    
    <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.10.0/css/all.css" integrity="sha384-AYmEC3Yw5cVb3ZcuHtOA93w35dYTsvhLPVnYs9eStHfGJvOvKxVfELGroGkvsg+p" crossorigin="anonymous"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    </head>
    
    <body>

        <div id="formWrap">
        <?php 
        // 2.2.1 ) validation error  ******************************************************************
        if ($empty_flag == 1) 
        {
        ?>
            <div align="center">

            <div class="py-3 text-center">
            <h4 class="fontsize-1 font-weight-bold text-danger"><br><br><br><br><br>入力にエラーがあります。</h4>
            <div class="py-3 fontsize-4 d-none d-md-block">下記をご確認の上、「戻る」ボタンにて修正をお願い致します。</div>
            <div class="py-3 text-left fontsize-4 d-block d-md-none">下記をご確認の上、「戻る」ボタンにて修正をお願い致します。</div>
            </div>

            <?php echo $errm; ?>
            <br /><br />
            <div class="row d-flex justify-content-center">
                <button type="button" class="col-5 col-md-3 btn btn-2 btn-submit fontsize-3 blue-royal bg-blue-white button-prepage" onClick="history.back()">
                    戻る
                </button>
                
            </div>
            </div>
        <?php 
        } 
        else 
        { 
        // 2.2.1 ) validation success : 確認画面  **********************************************************
        ?>
            <div class="py-3 mt-5 text-center">
                <h3 class="kakunin fontsize-1 font-weight-bold blue-royal">確認画面</h3>
            </div>

            <p align="center" >以下の内容で間違いがなければ、「送信」ボタンを押してください。</p>
            <form action="<?php echo h($_SERVER['SCRIPT_NAME']); ?>" method="POST" id="mailSendForm">
                <table class="formTable">
                <?php echo confirmOutput($mst_datas, $_POST, $_FILES);//入力内容を表示?>
                </table>
                <p align="center"><input type="hidden" name="mail_set" value="confirm_submit">
                <input type="hidden" name="httpReferer" value="<?php echo h($_SERVER['HTTP_REFERER']);?>">
            </form>

            <div class="row mb-5 mx-0 d-flex align-items-center justify-content-center">

                <button type="submit" form="mailSendForm" class="col-5 col-md-3 btn btn-1 rounded btn-submit fontsize-3 py-2 px-0 mx-5 mt-4 d-flex align-items-center justify-content-center button-prepage" id="sendbutton" style="cursor:pointer;">
                    <!-- <span class="spinner-border spinner-border-sm" id="loading" role="status" aria-hidden="true" style="display:none;"></span> -->
                    <span id="sendtext">送信</span>
                </button>
                <div class="col-12"></div>
                <div class="col-5 col-md-3 btn btn-2 btn-submit mx-auto mt-5 text-center blue-royal fontsize-3 bg-blue-white button-prepage" id="pageBack" onclick="history.back()" style="cursor:pointer;">
                    戻る
                </div>

            </div>

        <?php 
        } 
        ?>
        </div>
    
    </body>
    </html>

<?php
}
//2.2) 確認画面 : END


//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
//2.3) メール送信完了！
//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
if (($jumpPage == 0 && $sendmail == 1) || 
    ($jumpPage == 0 && ($confirmDsp == 0 && $sendmail == 0))) 
{
?>
    <!DOCTYPE HTML>
    <html lang="ja">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
    <meta name="format-detection" content="telephone=no">
    <title>完了画面</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css" integrity="sha384-TX8t27EcRE3e/ihU7zmQxVncDAy5uIKz4rEkgIXeMed4M0jlfIDPvg6uqKI2xXr2" crossorigin="anonymous">
    <link rel="stylesheet" href="css/stylesheet.css" />
    <link rel="stylesheet" href="css/stylemail.css" />

    <script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
    
    </head>
    
    <body>
        <div align="center">

        <?php 
        if ($empty_flag == 1) 
        { ?>
            <h4>入力にエラーがあります。</h4>
            <h4 class="pt-5">下記をご確認の上、「戻る」ボタンにて修正をお願い致します。</h4>
            <div style="color:red"><?php echo $errm; ?></div>
            <br /><br />
            <input type="button" class="button-prepage" value="戻る" onClick="history.back()">
        <?php 
        } 
        else 
        { ?>

            <div class="row pt-5 d-flex align-items-center justify-content-center">
                <div class="col-12 mt-4 fontsize-1 font-weight-bold d-none d-md-block" style="color: #177cbf;">
                    <br><br><br>お問い合わせありがとうございました。
                </div>
                <div class="col-12 mt-4 fontsize-1 font-weight-bold d-block d-md-none" style="color: #177cbf;">
                    <br><br>お問い合わせ<br>ありがとうございました。
                </div>
                <div class="col-12 fontsize-3 pt-5">送信は正常に完了しました</div>

                <div class="col-8 col-md-3 mt-5 py-2 btn-2 btn-submit rounded-lg fontsize-3 font-weight-bold blue-royal button-toppage" onclick="location.href='<?php echo $site_top ;?>'" style="color: #0a4893; cursor:pointer;">
                トップページへ戻る
                </div>
            </div>
        <?php 
        }
        ?>

        </div>
    </body>
    </html>
<?php
}

//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
//2.4) 確認画面無しの場合の表示、指定のページに移動する設定の場合、エラーチェックで問題が無ければ指定ページヘリダイレクト
//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
elseif (($jumpPage == 1 && $sendmail == 1) || $confirmDsp == 0) 
{
    if ($empty_flag == 1) 
    { ?>
        <div align="center">
            <h4>入力にエラーがあります。下記をご確認の上「戻る」ボタンにて修正をお願い致します。</h4>
            <div style="color:red"><?php echo $errm; ?></div>
            <br /><br />
            <input type="button" value="戻る" onClick="history.back()">
        </div>
    <?php
    } 
    else 
    {
        header("Location: ".$site_top);
    }
}
//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
// END OF CONTENTS
//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
?>