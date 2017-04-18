<?php
// ini_set('display_errors', true);

/*****************************************************************************/
/************************ Настройки параметров *******************************/
/*****************************************************************************/

// Корневой каталог
$hosting_path = '/home/s/saw10332';

// Какие форматы файлов проверять
$formats_file = array('php', 'js', 'htaccess', 'gif');

// Исключаемые папки
$exclude_path = array(
	$hosting_path . '/tmp'
);

// Исключаемые файлы
$exclude_files = array(
	$hosting_path . '/file_name'
);

// Частота проверки. Пусть каждые 2 часа
$last_check  = mktime(date('H')-2, date('i'), 0, date("m")  , date("d"), date("Y"));

// Название хостинга, для письма
$hosting_name = 'saw10332';

// Email куда слать уведомления
$admin_email = 'ildar@sawtech.ru';


/*****************************************************************************/
/**************************** Тело обработки *********************************/
/*****************************************************************************/

$res = get_files($hosting_path);

// Если все чисто, уйдем из скрипта
if( !$res ) exit;

// Придется формировать письмо с измененными файлами
$filecontent = "Дата изменения атрибутов;Дата изменения содержимого;Путь\n";
foreach ($res as $itm) {
	$filecontent .= $itm['time_cont'] .';'. $itm['time_attr'] .';'. $itm['path'] . "\n";	
};
 
 
$file_name = $hosting_path.'/change_files.csv';
$file_hand = fopen($file_name, 'w+');
fwrite($file_hand, $filecontent);
fclose($file_hand);

$subject = 'Обнаружены изменненные файлы на сервере '.$hosting_name;
$message = '';
$from = $hosting_name.'@host.ru';

// отправка списка изменных файлов
send_mail($admin_email, $from, $subject, $message, $file_name);

// Удалим файл, чтобы не мозолил глаза
unlink($file_name);







/******************************************************************************/
/****************** Рекурсивный обход всех подпапок ***************************/
/******************************************************************************/
function get_files($dir = "./"){
	
	global $hosting_path, $last_check, $formats_file, $exclude_path, $exclude_files;
	$result = array();
	$itm = array();
 
	if ($handle = opendir($dir)) {     
		while (false !== ($item = readdir($handle))) {     
			if (is_file("$dir/$item") && !in_array("$dir/$item", $exclude_files)) {
				$time_cont = filemtime("$dir/$item");	
				$time_attr = filectime("$dir/$item");
				$format = get_file_type("$dir/$item");
 
				if( in_array($format, $formats_file)
					&& ($time_cont > $last_check || $time_attr > $last_check) 
				){
					$itm['time_cont'] = date('d.m.Y H:i', $time_cont);
					$itm['time_attr'] = date('d.m.Y H:i', $time_attr);
					$itm['path'] = str_replace($hosting_path.'/', '', "$dir/$item");
					$result[] = $itm;
				};
 
			} elseif (is_dir("$dir/$item") && ($item != ".") && ($item != "..") && ($item != ".." && !in_array("$dir/$item", $exclude_path)) ) {
				$result = array_merge($result, get_files("$dir/$item"));
			}
		} 
		closedir($handle);
	};
	return $result; 
};



/******************************************************************************/
/********************* Определение расширение файла ***************************/
/******************************************************************************/
function get_file_type($link){
	$path_info = pathinfo($link);
	return $path_info['extension'];
}

/******************************************************************************/
/*************************** Отправка email ***********************************/
/******************************************************************************/
/*
 	* $to 			- email доставки
 	* from 			- от кого письмо
 	* $subject 		- Тема сообщения
 	* $html 		- тело сообщения
	* $file_path 	- путь до файла в отношении сервера
*/
function send_mail($to, $from, $subject, $html, $file_path = false){
	$EOL = "\r\n"; // ограничитель строк, некоторые почтовые сервера требуют \n - подобрать опытным путём

	if ($file_path) {
		$fp = fopen($file_path, "rb");   
		if ( !$fp ) { 
			print "Cannot open file";
			exit();
		}
		$file = fread($fp, filesize($file_path));
		fclose($fp);   

		$file_name = basename($file_path); // Имя файла

		$boundary = "--".md5(uniqid(time()));  // любая строка, которой не будет ниже в потоке данных.  
		$headers  = "MIME-Version: 1.0;$EOL";   
		$headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"$EOL";  
		$headers .= "From: $subject <$from>";  

		$message  = "--$boundary$EOL";   
		$message .= "Content-Type: text/html; charset=utf-8$EOL";   
		$message .= "Content-Transfer-Encoding: base64$EOL";   
		$message .= $EOL; // раздел между заголовками и телом html-части 
		$message .= chunk_split(base64_encode($html)); 

		$message .=  "$EOL--$boundary$EOL";   
		$message .= "Content-Type: application/octet-stream; name=\"$file_name\"$EOL";   
		$message .= "Content-Transfer-Encoding: base64$EOL";
		$message .= "Content-Disposition: attachment; filename=\"$file_name\"$EOL";   
		$message .= $EOL; // раздел между заголовками и телом прикрепленного файла 
		$message .= chunk_split(base64_encode($file));
		$message .= "$EOL--$boundary--$EOL";   
	} else {
		$headers  = "MIME-Version: 1.0$EOL";
		$headers .= "Content-type: text/html; charset=utf-8$EOL";
		$headers .= "From: $subject! <$from>" . $EOL;
		$message = $html;
	}	


	if(!mail($to, $subject, $message, $headers)){
		return false;
	} else {
		return true;  
	}
}
?>