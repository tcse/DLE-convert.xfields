<?php
/*
=====================================================
 DLE-convert.xfields for DataLife Engine - by TCSE
-----------------------------------------------------
 http://tcse-cms.com/
-----------------------------------------------------
 Copyright (c) 2016 TCSE
=====================================================
 Автор скрипта: Виктор Ермаков devops@tcse-cms.com
=====================================================
 Файл: convert.xfields.php
-----------------------------------------------------
 Назначение: конвертация дополнительного поля text в формат yesorno
=====================================================
*/

$onStageNews = 1;
$fieldName = 'tablon';

@error_reporting ( E_ALL ^ E_WARNING ^ E_DEPRECATED ^ E_NOTICE );
@ini_set ( 'error_reporting', E_ALL ^ E_WARNING ^ E_DEPRECATED ^ E_NOTICE );

// Стартовые действия
define( "DATALIFEENGINE", true );
define( "ROOT_DIR", dirname( __FILE__ ) );
define( "ENGINE_DIR", ROOT_DIR."/engine" );
require( ENGINE_DIR."/classes/mysql.php" );
require( ENGINE_DIR."/data/config.php" );
require( ENGINE_DIR."/data/dbconfig.php" );
require( ROOT_DIR."/language/".$config['langs']."/website.lng" );

function totranslit( $var, $lower = true, $punkt = true ){
	global $langtranslit;
	
	if ( is_array($var) ) return "";

	$var = str_replace(chr(0), '', $var);

	if (!is_array ( $langtranslit ) OR !count( $langtranslit ) ) {
		$var = trim( strip_tags( $var ) );

		if ( $punkt ) $var = preg_replace( "/[^a-z0-9\_\-.]+/mi", "", $var );
		else $var = preg_replace( "/[^a-z0-9\_\-]+/mi", "", $var );

		$var = preg_replace( '#[.]+#i', '.', $var );
		$var = str_ireplace( ".php", ".ppp", $var );

		if ( $lower ) $var = strtolower( $var );

		return $var;
	}
	
	$var = trim( strip_tags( $var ) );
	$var = preg_replace( "/\s+/ms", "-", $var );
	$var = str_replace( "/", "-", $var );

	$var = strtr($var, $langtranslit);
	
	if ( $punkt ) $var = preg_replace( "/[^a-z0-9\_\-.]+/mi", "", $var );
	else $var = preg_replace( "/[^a-z0-9\_\-]+/mi", "", $var );

	$var = preg_replace( '#[\-]+#i', '-', $var );
	$var = preg_replace( '#[.]+#i', '.', $var );

	if ( $lower ) $var = strtolower( $var );

	$var = str_ireplace( ".php", "", $var );
	$var = str_ireplace( ".php", ".ppp", $var );

	if( strlen( $var ) > 200 ) {
		
		$var = substr( $var, 0, 200 );
		
		if( ($temp_max = strrpos( $var, '-' )) ) $var = substr( $var, 0, $temp_max );
	
	}
	
	return $var;
}

// Должна возвратить количество записей
function returnCount(){
	global $db;
	$result = $db->super_query( "SELECT COUNT(*) as count FROM ".PREFIX."_post" );
	return $result['count'];
}

// Должна возвратить количество обрабатываемых записей за один этап
function countOnStage(){
	global $onStageNews;
	return $onStageNews;
}

// Действия обработки записей ( 0, 3 )
function doReplaced( $start, $end ){
	global $count, $stageCount, $thisStage, $replacedTrue, $replacedCount, $residueCount, $maxStage, $fieldName;
	global $db, $config;
	
	$result = $db->query( "SELECT `id`,`xfields` FROM ".PREFIX."_post ORDER BY `id` LIMIT {$start}, {$stageCount}" );
	if( $db->num_rows( $result ) > 0 )
		{
			while( $row = $db->get_row( $result ) )
				{
					if( trim( $row['xfields'] ) )
						{
							$id = $row['id'];
							$replace = false;
							$xfields = explode( "||", $row['xfields'] );
							foreach( $xfields as $key => $xfield )
								{
									$xfield = explode( "|", $xfield );
									if( $xfield[0] == $fieldName ){
										$xfield[1] = $xfield[1] ? 1 : '';
										$xfields[ $key ] = "{$xfield[0]}|{$xfield[1]}";
										$replace = true;
									}
								}
								
							if( $replace !== true ){
								$xfields[] = "{$fieldName}|0";
								$replace = true;
							}
							
							if( $replace === true )
								{
									$xfields = $db->safesql( implode( '||', $xfields ) );
									$db->query( "UPDATE ".PREFIX."_post SET `xfields`='{$xfields}' WHERE `id`='{$id}' LIMIT 1" );
									plusReplacedTrue( 1 );
								}
						}
				}
		}
			else
		{
			die( "Записи не найдены!!!" );	
		}	
}

// Обновление количества успешных обработанных записей
function plusReplacedTrue( $plusReplaced ){
	global $newReplacedTrue;
	$newReplacedTrue = $newReplacedTrue + $plusReplaced;
}

//-------------------------------------------------------
$replacedTrueEcho = true; // Показывать количество успешных обработанных записей?
//-------------------------------------------------------
$nextInterval = false;
$count = returnCount(); // Всего записей
$stageCount = countOnStage(); // Записей за один этап
$thisStage = intval( $_REQUEST['stage'] ); // Текущий этап
$replacedTrue = intval( $_REQUEST['replacedTrue'] ); // Успешно сделано
$newReplacedTrue = $replacedTrue; // Новых успешно сделано
$replacedCount = $thisStage * $stageCount > $count ? $count : $thisStage * $stageCount; // Уже обработано записей
$residueCount = intval( $count - $replacedCount ); // Осталось обработать записей
$maxStage = $count > ( intval( $count / $stageCount ) * $stageCount ) ? intval( $count / $stageCount ) + 1 : intval( $count / $stageCount ); // Количество этапов
//-------------------------------------------------------

if( $residueCount > 0 )
	{
		doReplaced( $replacedCount, ( $replacedCount + $stageCount ) );
		$nextInterval = true;
	}

$replacedTrueEchoText = $replacedTrueEcho === true ? "Успешно сделано: {$replacedTrue}<br />" : "";

echo <<<HTML
<html>
<body>
<head>
	<title>Интервал система</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<body>
Всего: {$count}<br />
Проверено: {$replacedCount}<br />
{$replacedTrueEchoText}
Осталось: {$residueCount}<br /><br />
HTML;


if( $nextInterval === true )
	{
		$host = $_SERVER['HTTP_HOST'];
		$newStage = $thisStage + 1;
		$newLocation = $_SERVER['PHP_SELF']."?stage={$newStage}&replacedTrue={$newReplacedTrue}";
		echo <<<HTML
		Ожидайте, вы автоматически переместитесь, или нажмите на: <a href="{$newLocation}">http://{$host}{$newLocation}</a>
		<script language="javascript" type="text/javascript">
			setTimeout( function(){
				location.href = "{$newLocation}";
			}, 1000 );		
		</script>
HTML;

	}
		else
	{
		echo <<<HTML
		Готово
HTML;
	}

echo <<<HTML

</body>
</html>
HTML;

?>
