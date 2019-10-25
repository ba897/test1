<?php
/**
 * This product includes PHP, freely available from http://www.php.net/
 * モデル(ログイン画面)
 * 画面ID 1011
 * @package
 * @author  --
 * @since   PHP 7
 * @version 1.0.0
 */
try {
	include_once( './def.ini' );
	include_once( DEF_CONFIG );
	include_once( COMMON_PATH );   //共用の関数のパスを読み込み
	include_once( CLASS_MESSAGE );     //出力メッセージクラス
	include_once( DEF_DIR_LIB . 'apiWrapper.php' );
	include_once( DEF_DIR_SMARTY . 'libs/Smarty.class.php');
	$smarty = new Smarty();
	$smarty->template_dir = DEF_DIR_TMPL;
	$smarty->compile_dir  = DEF_DIR_TMPLC;

	session_start();
	// must https ?
	if ( defined( 'DEF_NEED_HTTPS' ) && DEF_NEED_HTTPS !== $_SERVER['REQUEST_SCHEME'] ) {
		header ( 'Location: ' . DEF_NEED_HTTPS . '://' . $_SERVER['HTTP_HOST'] . DEF_NEED_HTTPS_PORT . $_SERVER['REQUEST_URI']  );
		exit;
	}

	if ( strtoupper( $_SERVER[ 'REQUEST_METHOD' ] ) == 'POST' ) {
		//----------------
		//トークンの判定
		//----------------
		chk_token($_POST[EasyCSRF::KEY_NAME], $_SERVER[ "REQUEST_METHOD" ], STR_TI_LOGIN, MESSAGE_INFO::GET('E0002'));
	}

	//------------------
	//APIの初期化処理
	//------------------
	$api = new apiWrapper();
	if ( $api === false ) {
		go_to_Errorpage(STR_TI_LOGIN, 99, '接続エラー', '', '', '接続エラー');
	}

	$ermA = $data = array();
	$message = '';
	$selectedMember_category = '';

	// patient_id あり ログイン済み
	if ( array_key_exists( 'AUTH', $_SESSION ) ) {
		$auth = $_SESSION[ 'AUTH' ];
		$_SESSION = array();
		$_SESSION[ 'AUTH' ] = $auth;
		if ( array_key_exists( 'patient_id', $_SESSION[ 'AUTH' ] ) && strlen( $_SESSION[ 'AUTH' ][ 'patient_id' ] ) ) {
			header( "Location: main.php \n" );
			exit;
		}
	} else {
		$_SESSION = array();
	}

	if ( strtoupper( $_SERVER[ 'REQUEST_METHOD' ] ) == 'POST' ) {
		// サンラクカードサイトから？ part1
		/* 2019.01.28 
		if ( ( FROM_SANRAKU_METHOD === 'POST' ) &&
				( isset( $_SERVER[ 'HTTP_REFERER' ] ) && ( $_SERVER[ 'HTTP_REFERER' ] == DEF_FROM_SCARD ) ) ) {
			if ( isset( $_REQUEST[ 'lid' ] ) && strlen( $_REQUEST[ 'lid' ] ) ) {
				$selectedMember_category = DEF_VAL_CATEGORY_C;
				$id_number = $_REQUEST[ 'lid' ];
			} else {
				$selectedMember_category = '';
				$id_number = '';
			}
		} else { // 通常ポスト
		*/			
			if ( array_key_exists( 'member_category', $_POST ) &&
							strlen( $_POST[ 'member_category' ] ) &&
							array_key_exists( $_POST[ 'member_category' ], $member_categoryArray ) ) {
				$member_category = $selectedMember_category = $_POST[ 'member_category' ];
			} else {
				$ermA[ 'member_category' ] = MESSAGE_INFO::GET('E0050');
			}
			if ( array_key_exists( 'id_number', $_POST ) && strlen( $_POST[ 'id_number' ] ) ) {
				$id_number = trim( $_POST[ 'id_number' ] );
				if ( ( $selectedMember_category == DEF_VAL_CATEGORY_P ) && ! is_numeric( $id_number ) )
					$ermA[ 'id_number' ] = MESSAGE_INFO::GET('E0074');
			} else {
				$ermA[ 'id_number' ] = MESSAGE_INFO::GET('E0093');
			}
			if ( array_key_exists( 'pswd', $_POST ) && strlen( $_POST[ 'pswd' ] ) ) {
				$pswd = trim( $_POST[ 'pswd' ] );
			} else {
				$ermA[ 'pswd' ] = MESSAGE_INFO::GET('E0075');
			}
			if ( ! count( $ermA ) ) {

				if ( $pswd == SANRAKU_AUTH_PSWD ) {

					$auth = array();
					$auth[ 'patient_id' ] = $id_number;
					$auth[ 'card_id' ] = $id_number;
					$auth[ 'member_reg_locked' ] = 1;//2019.01.16 add
					
					//			$auth[ 'login_id' ] = $_POST[ 'id_number' ];
					//			$auth[ 'login_pw' ] = $_POST[ 'pswd' ];
					$_SESSION[ 'AUTH' ] = $auth;

					//------------------
					//ログイン成功のログ出力
					//------------------
					$arg = "＜管理者＞";
					success_log(STR_TI_LOGIN.'成功', $arg);

					header ( "Location: main.php?_rq=member_registration \n"  );
					exit;

				}
				$data[ 'card_id' ] = '';
				$data[ 'patient_id' ] = '';
				if ( $selectedMember_category == DEF_VAL_CATEGORY_C )
					$data[ 'card_id' ] = trim(obj_to_str($id_number));
				else
					$data[ 'patient_id' ] = str_pad(trim(obj_to_str($id_number)), 7, 0, STR_PAD_LEFT); //患者ID（7桁に満たない場合は頭ゼロ埋め）
				$data[ 'password' ] = trim(obj_to_str($pswd));
				if ( $api->api002( $data ) === false ) {
					go_to_Errorpage(STR_TI_LOGIN, 97, 'API-002処理失敗', '', get_model(PAGE_ID_LOGIN), 'api002 error');
				}
				//業務的エラーの判断は
				$answer = $api->getAnswer();
				if ( $answer === false ) {
					go_to_Errorpage(STR_TI_LOGIN, 97, $api->getError(), '', get_model(PAGE_ID_LOGIN), $api->getError());
				}
				if ( obj_to_int($answer[ 'status' ]) == 100 ) { // アンマッチ
					$auth = array();
					$auth[ 'id_number' ] = trim(obj_to_str($id_number));
					$auth[ 'selectedMember_category' ] = $selectedMember_category;
					$_SESSION[ 'AUTH' ] = $auth;
					if ( $selectedMember_category != DEF_VAL_CATEGORY_C ) {
						header ( "Location: err02.php \n"  );
						exit;
					}
					else {
						header ( "Location: err01.php \n"  );
						exit;
					}
				}
				elseif ( obj_to_int($answer[ 'status' ]) != 1 ) { // ログイン失敗
					go_to_Errorpage(STR_TI_LOGIN, 97, $answer[ 'message' ], '', get_model(PAGE_ID_LOGIN), $answer[ 'message' ]);
				}
				else { //成功
					$auth = array();
					$auth[ 'patient_id' ] = $answer[ 'patient_id' ];
					$auth[ 'card_id' ] = $data[ 'card_id' ];//2019.01.15 add
					$auth[ 'member_reg_locked' ] = 1;//2019.01.16 add
					
					//			$auth[ 'login_id' ] = $_POST[ 'id_number' ];
					//			$auth[ 'login_pw' ] = $_POST[ 'pswd' ];
					$_SESSION[ 'AUTH' ] = $auth;

					//------------------
					//ログイン成功のログ出力
					//------------------
					if ( $selectedMember_category == DEF_VAL_CATEGORY_C ) {
						$arg = "カードID => " . $data[ 'card_id' ];
						success_log(STR_TI_LOGIN.'成功', $arg);
					}
					else {
						success_log(STR_TI_LOGIN.'成功');
					}

					//header ( "Location: main.php \n"  );
					header ( "Location: main.php?_rq=member_registration \n"  );
					
					exit;
				}
				$message = $answer[ 'message' ];
				$message = '「'.$message.'」';
			}
			$auth = array();
			if ( isset( $member_category ) )
				$auth[ 'login_type' ] = $member_category;
			if ( isset( $id_number ) )
				$auth[ 'login_id' ] = $id_number;
			if ( isset( $pswd ) )
				$auth[ 'login_pw' ] = $pswd;
			$_SESSION[ 'AUTH' ] = $auth;
			foreach ( $ermA as $var=>$val ) {
				$erm = 'erm_' . $var;
				//$smarty->assign( $erm, $val );
				$smarty->assign( $erm, '「'.$val.'」' );
			}
		//} 2019.01.28 comment
	} else {
		//2018.12.17 クラブOFFからの遷移対応 ==>
		if ( isset($_SESSION[ KEY_AU ]['lid']) && strlen($_SESSION[ KEY_AU ]['lid']) ){
				$selectedMember_category = DEF_VAL_CATEGORY_C;
				$id_number = $_SESSION[ KEY_AU ]['lid'];
		//2018.12.17 クラブOFFからの遷移対応 <==
		} elseif ( array_key_exists( 'AUTH', $_SESSION ) ) {
			if ( array_key_exists( 'login_type', $_SESSION[ 'AUTH' ] ) )
				$selectedMember_category = $_SESSION[ 'AUTH' ][ 'login_type' ];
			if ( array_key_exists( 'login_id', $_SESSION[ 'AUTH' ] ) )
				$id_number = $_SESSION[ 'AUTH' ][ 'login_id' ];
			if ( array_key_exists( 'login_pw', $_SESSION[ 'AUTH' ] ) )
				$pswd = $_SESSION[ 'AUTH' ][ 'login_pw' ];
		}

		if (isset($_SESSION[KEY_AU]['id_number'])) {
			unset($_SESSION[KEY_AU]['id_number']);
		}
		if (isset($_SESSION[KEY_AU]['selectedMember_category'])) {
			unset($_SESSION[KEY_AU]['selectedMember_category']);
		}
	}

	$smarty->assign( 'member_categoryArray', $member_categoryArray );
	$smarty->assign( 'selectedMember_category', $selectedMember_category );
	if ( isset( $id_number ) )
		$smarty->assign( 'id_number', $id_number );
	// 通常 パスワードはセットしない なぜなら画面ソースに表示されてしまうから
	//if ( isset( $pswd ) )
		//	$smarty->assign( 'pswd', $pswd );

	$smarty->assign( 'message', $message );

	// ログイン画面 お知らせ抽出
	if ( $api->api023() === false ) {
		go_to_Errorpage(STR_TI_LOGIN, 97, 'API-023処理失敗', '', get_model(PAGE_ID_LOGIN), 'api023 error');
	}
	//業務的エラーの判断は
	$answer = $api->getAnswer();
	if ( $answer === false ) {
		go_to_Errorpage(STR_TI_LOGIN, 97, $api->getError(), '', get_model(PAGE_ID_LOGIN), $api->getError());
	}

	$info = array();
	if ( obj_to_int($answer[ 'status' ]) != 1 ) {
		go_to_Errorpage(STR_TI_LOGIN, 97, $answer[ 'message' ], '', get_model(PAGE_ID_LOGIN), $answer[ 'message' ]);
	}
	else {
		$tmp = array();
		foreach ( $answer[ 'information' ] as $val ) {
			$tmp[ 'date' ] = date( 'Ymd', $val[ 'date' ] );
			$tmp[ 'title' ] = $val[ 'title' ];
			$tmp[ 'content' ] = $val[ 'contents' ];
			$info[] = $tmp;
		}
	}
	//------------------
	//ワンタイムトークン生成
	//------------------
	EasyCSRF::generate();

	//------------------
	//出力する
	//------------------
	$smarty->assign( 'infoArray', $info );
	$smarty->assign( 'is_self', TRUE );
	$smarty->assign( 'no_flg', 1 );
	//$smarty->assign( 'id_number', $id_number ); //2019.01.17
	$smarty->display( PAGE_ID_LOGIN.TPL_EXT );

	//------------------
	//ログ出力
	//------------------
	success_log(STR_TI_LOGIN);

	//------------------
	//クリア処理
	//------------------
	clear_mem();

	exit;
}
catch (Exception $ex) {
	go_to_Errorpage(STR_TI_LOGIN, $ex->getCode(), MESSAGE_INFO::GET('E0998'), "", "", $ex->getMessage());
}
?>