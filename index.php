<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Untitled Document</title>
<style>
	.dn-1{
		color: black;
		}
	.dn-1:hover{
		text-decoration: underline;
		
		}



</style>
<?php
		ini_set('max_execution_time', 180);
		ini_set('memory_limit', '-1');
		error_reporting(0);
		date_default_timezone_set('asia/ho_chi_minh');
		if (!isset($_SESSION)) session_start();
		include "connectdb.php";

		class WavFile{
			private static $HEADER_LENGTH = 44;

			public static function ReadFile($filename) {
	            $filesize = filesize($filename);
	            if ($filesize<self::$HEADER_LENGTH)
	                return false;           
	            $handle = fopen($filename, 'rb');
	            $wav = array(
	                    'header'    => array(
	                        'chunkid'       => self::readString($handle, 4),
	                        'chunksize'     => self::readLong($handle),
	                        'format'        => self::readString($handle, 4)
	                        ),
	                    'subchunk1' => array(
	                        'id'            => self::readString($handle, 4),
	                        'size'          => self::readLong($handle),
	                        'audioformat'   => self::readWord($handle),
	                        'numchannels'   => self::readWord($handle),
	                        'samplerate'    => self::readLong($handle),
	                        'byterate'      => self::readLong($handle),
	                        'blockalign'    => self::readWord($handle),
	                        'bitspersample' => self::readWord($handle)
	                        ),
	                    'subchunk2' => array( //INFO chunk is optional, but I need it for this project's audio file
	                        'id'            => self::readString($handle, 4),
	                        'size'			=> self::readLong($handle),
	                        'data'          => null
	                        ),
	                    'subchunk3' => array(
	                    	'id'			=> null,
	                    	'size'			=> null,
	                        'data'          => null
	                        )
	                    );
	            $wav['subchunk2']['data'] = fread($handle, $wav['subchunk2']['size']);
	            $wav['subchunk3']['id'] = self::readString($handle, 4);
	            $wav['subchunk3']['size'] = self::readLong($handle);
				$wav['subchunk3']['data'] = fread($handle, $wav['subchunk3']['size']);
	            fclose($handle);
	            return $wav;
		    }

		    private static function readString($handle, $length) {
		        return self::readUnpacked($handle, 'a*', $length);
		    }

		    private static function readLong($handle) {
		        return self::readUnpacked($handle, 'V', 4);
		    }

		    private static function readWord($handle) {
		        return self::readUnpacked($handle, 'v', 2);
		    }

		    private static function readUnpacked($handle, $type, $length) {
		        $r = unpack($type, fread($handle, $length));
		        return array_pop($r);
		    }
		}

		if(isset($_POST['upfilebtn'])){
			$fileName = $_FILES["upfile"]["tmp_name"];
			$fileType = strtolower($_FILES['upfile']['type']);

			if ($fileType == "audio/wav"){
				
				//Read audio file
					
				$wavFile = new WavFile;
				$tmp = $wavFile->ReadFile($fileName);
				unlink($fileName);

				//Get binary code of signature

				function BintoText($bin){
					$text = "";
					for($i = 0; $i < strlen($bin)/8 ; $i++)
						$text .= chr(bindec(substr($bin, $i*8, 8)));
					return $text;
				}

				$subchunk3data = unpack("H*", $tmp['subchunk3']['data']);

				$signature = "";
				for($i = 0; $i < 80; $i++){
					$signature .= substr(str_pad(base_convert(substr($subchunk3data[1], $i*2, 2), 16, 2), 8, '0', STR_PAD_LEFT), 7, 1);
				}
				$lenofsigndat = BintoText(substr($signature, 0, 80));
				if (is_numeric($lenofsigndat)){
					for($i = 80; $i < 80+$lenofsigndat*8; $i++){
						$signature .= substr(str_pad(base_convert(substr($subchunk3data[1], $i*2, 2), 16, 2), 8, '0', STR_PAD_LEFT), 7, 1);
					}
					$signdat = BintoText(substr($signature, 80, $lenofsigndat*8));
				}
			}
		}

		

		if (isset($_SESSION['user'])){
			$qr = $conn->prepare("select permission from user where id = '" . $_SESSION['user'] .  "';");
			$qr->execute();
			$rs_mypermission = $qr->fetch();

			if ($rs_mypermission['permission'] == "admin"){

				if(isset($_POST['upfilebtn1']) && isset($_POST['upfilesinger']) && isset($_POST['upfilesong'])){
					$fileName = $_FILES["upfile1"]["tmp_name"];
					$fileType = strtolower($_FILES['upfile1']['type']);

					if ($fileType == "audio/wav"){

						// Upload audio file to Google Drive
						
						require_once 'google-api-php-client-2.2.1/vendor/autoload.php';
						$client = new Google_Client();
						putenv('GOOGLE_APPLICATION_CREDENTIALS=google-api-php-client-2.2.1/service_account_keys.json');
						$client = new Google_Client();
						$client->addScope(Google_Service_Drive::DRIVE);
						$client->useApplicationDefaultCredentials();
						$service = new Google_Service_Drive($client);

						$content = file_get_contents($fileName);
						$fileMetadata = new Google_Service_Drive_DriveFile(array('name' => mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $_POST['upfilesinger'] . " - " . $_POST['upfilesong'] . ".wav")));
						$file = $service->files->create($fileMetadata, array(
						    'data' => $content,
						    'mimeType' => 'audio/wav',
						    'uploadType' => 'multipart',
						    'fields' => 'id'));
						$fileId = $file->id;
						unlink($fileName);

						//Share file to anyone

						$service->getClient()->setUseBatch(true);
						$batch = $service->createBatch();
						$filePermission = new Google_Service_Drive_Permission(array(
					    	'type' => 'anyone',
					    	'role' => 'reader',
						));
					    $request = $service->permissions->create($fileId, $filePermission, array('fields' => 'id'));
					    $batch->add($request, 'anyone');
					    $results = $batch->execute();
						$service->getClient()->setUseBatch(false);
						$fileUrl = "https://drive.google.com/file/d/" . $fileId . "/view?usp=sharing";
						
						// Record license to Database

						$qr = $conn->prepare("insert into multimedia (id, parentid, song, singer, url, type, owner) values (:id, :parentid, :song, :singer, :url, 'music', 'admin');");
						$qr->bindParam(":id", $fileId, PDO::PARAM_STR);
						$qr->bindParam(":parentid", $fileId, PDO::PARAM_STR);
						$qr->bindParam(":song", $_POST['upfilesong'], PDO::PARAM_STR);
						$qr->bindParam(":singer", $_POST['upfilesinger'], PDO::PARAM_STR);
						$qr->bindParam(":url", $fileUrl, PDO::PARAM_STR);
						$qr->execute();
					}
				}
			}

			$qr = $conn->prepare("select id, song, singer from multimedia where type = 'music' and owner = '" . $_SESSION['user'] .  "';");
			$qr->execute();
			$rs_myplaylist = $qr->fetchAll();
		}

		$qr = $conn->prepare("select id, song, singer from multimedia where type = 'music' and owner = 'admin';");
		$qr->execute();
		$rs_allsongs = $qr->fetchAll();
	?>
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" />
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
		<link rel="stylesheet" href="style1.css" />
        <link rel="stylesheet" href="loader.css">

		<script type="text/javascript" src="soundmanager2/soundmanager2.js"></script>
		<script type="text/javascript" src="soundmanager2/script/bar-ui.js"></script>
		<link rel="stylesheet" href="soundmanager2/css/bar-ui.css">
		<link rel="stylesheet" href="style.css" />
		<link href="https://fonts.googleapis.com/css?family=Oregano:400|Open+Sans:400|Roboto+Condensed:400,600,700" rel="stylesheet">
        <script>
	function removeloader(){
		var ele = document.getElementById("loader");
		opacity = 1;
		id=setInterval(fade,5);
		function fade(){
			if( opacity <= 0){
				clearInterval(id);
				ele.style.display = "none";
			}else{
			ele.style.opacity = opacity;
			opacity = opacity - 0.005;
			}
		}
	}
</script>
</head>

<body onload="removeloader()">
	<div id="loader" align="center">
	<img src="images/loading-cylon-red.svg" width="500" height="25" id="loader-cylon"><br>
	<div id="loading-txt">
		<b><img src="images/1.png" /></b>
	</div><br>
	<img src="images/loading-bubbles.svg" width="64" height="64" id="loader-bubble">
</div>
<section class="navbar">
      <div class="container">
				<div class="navbar-header">
  <span class="navbar-header"><a href="index.php" title="Tn-WaterMarkingDemo"><img src="images/1.png" alt="Tn-WaterMarkingDemo" width="177" height="67" 						/></a></span><span class="navbar-header"></div>
  	
  <?php
					if (isset($_SESSION['user'])){
						echo "<a id=\"fn_login\" href=\"logout.php\" title=\"Sign out\">Đăng xuất</a>";
						echo "<p id=\"username\">Xin chào, " . $_SESSION['user'] . "!</p>";
					}
					else{ 
						echo "<a id=\"fn_login\" href=\"login.php\" title=\"Sign in\">Đăng nhập</a>";
						
					}
				?>
  </span></section>
  		<section class="mainbar">
		    <div class="container">
		      	<ul class="nav mainbar-list wow fadeInDown" data-wow-duration="0.5s">
		        	<li id="buy-more-song" class="active">
		          		<a class="highlight" href="#buymoresong" title="Mua bài hát">
		            		<i class="fa fa-shopping-cart"></i> Mua bài hát
		          		</a>
		       		</li>
		       		<li id="get-signature">
		          		<a href="#getsignature" title="Xem chữ kí">
		            		<i  ><img src="images/signature1.png" /></i> Xem chữ kí
		          		</a>
		       		</li>
		       		<?php
		       			if (isset($rs_mypermission['permission'])){
		       				if ($rs_mypermission['permission'] == "admin"){
					       		echo "<li id=\"upload-new-song\">
					          		<a href=\"#uploadnewsong\" title=\"Upload nhạc \">
					            		<i class=\"fa fa-upload\"></i> Upload nhạc 
					          		</a>
					       		</li>";
				       		}
		       			}
		       		?>
		      </ul>
		    </div>
		</section>
      
       
       
   
   
   		<div class="mycontent">
        	
        	<div class="audioplayer">
            		<div class="sm2-bar-ui full-width fixed">
 			<div class="bd sm2-main-controls">
				<div class="sm2-inline-texture"></div>
				<div class="sm2-inline-gradient"></div>
  				<div class="sm2-inline-element sm2-button-element">
					<div class="sm2-button-bd">
						<a href="#play" class="sm2-inline-button sm2-icon-play-pause">Play / pause</a>
					</div>
  				</div>
  				<div class="sm2-inline-element sm2-inline-status">
					<div class="sm2-playlist">
						<div class="sm2-playlist-target">
							<noscript><p>JavaScript is required.</p></noscript>
						</div>
					</div>
					<div class="sm2-progress">
						<div class="sm2-row">
							<div class="sm2-inline-time">0:00</div>
							<div class="sm2-progress-bd">
								<div class="sm2-progress-track">
									<div class="sm2-progress-bar"></div>
									<div class="sm2-progress-ball"><div class="icon-overlay"></div></div>
								</div>
							</div>
							<div class="sm2-inline-duration">0:00</div>
						</div>
					</div>
				</div>
				<div class="sm2-inline-element sm2-button-element sm2-volume">
					<div class="sm2-button-bd">
						<span class="sm2-inline-button sm2-volume-control volume-shade"></span>
						<a href="#volume" class="sm2-inline-button sm2-volume-control">volume</a>
					</div>
				</div>
				<div class="sm2-inline-element sm2-button-element">
					<div class="sm2-button-bd">
						<a href="#prev" title="Previous" class="sm2-inline-button sm2-icon-previous">&lt; previous</a>
					</div>
				</div>
				<div class="sm2-inline-element sm2-button-element">
					<div class="sm2-button-bd">
						<a href="#next" title="Next" class="sm2-inline-button sm2-icon-next">&gt; next</a>
					</div>
				</div>

				<div class="sm2-inline-element sm2-button-element">
					<div class="sm2-button-bd">
						<a href="#repeat" title="Repeat playlist" class="sm2-inline-button sm2-icon-repeat">&infin; repeat</a>
					</div>
				</div>
				<div class="sm2-inline-element sm2-button-element sm2-menu">
					<div class="sm2-button-bd">
						<a href="#menu" class="sm2-inline-button sm2-icon-menu">menu</a>
					</div>
				</div>
			</div>
			<div class="bd sm2-playlist-drawer sm2-element">
				<div class="sm2-inline-texture">
					<div class="sm2-box-shadow"></div>
				</div>
  				<div class="sm2-playlist-wrapper">
	    			<ul class="sm2-playlist-bd">
	    				<?php
	    					if (isset($_SESSION['user'])){
		    					foreach ($rs_myplaylist as $key => $value){
					 				echo "<li>
											<div class=\"sm2-row\">
												<div class=\"sm2-col sm2-wide\">
													<a href=\"http://docs.google.com/uc?export=open&id=" . $value['id'] . "&type=.wav\"><b>" . $value['singer'] . "</b> - " . $value['song'] . "</a>
												</div>
												<div class=\"sm2-col\">
													<a href=\"http://docs.google.com/uc?export=open&id=" . $value['id'] . "\" target=\"_blank\" title=\"Tải bài hát này\" ><img src=\"download.png\"></a>
												</div>
											</div>
										</li>";
				 				}
			 				}
						?>
    				</ul>
  				</div>
 			</div>
		</div>
            
            </div>
			<table class="songstable">
				<thead>
					<tr>
						<th class="col-xs-1" >ID</th>
						<th class="col-xs-5" >Bài hát</th>
						<th class="col-xs-4" >Ca sĩ</th>
						<th class="col-xs-2" >Status</th>
					</tr>
				</thead>
				<tbody class="songstable_scrollbar">
					<?php
						$i = 1;
						foreach ($rs_allsongs as $key => $value) {
							echo "<tr class=\"" . ($i % 2 ? "odd" : "even") . "\">
									<td class=\"col-xs-1\">" . $i . "</td>
									<td class=\"col-xs-5\">" . $value['song'] . "</td>
									<td class=\"col-xs-4\">" . $value['singer'] . "</td>
									<td class=\"col-xs-2\">";

							if (isset($_SESSION['user'])){
								$qr = $conn->prepare("select id from multimedia where type = 'music' and owner = '" . $_SESSION['user'] .  "' and parentid = '" . $value['id'] . "' limit 1;");
								$qr->execute();
								$rs_isLicenced = $qr->fetch();
								if ($rs_isLicenced['id'] == ""){
									echo "<button id=\"" . $value['id'] . "\" class=\"btn btn-danger btn-mini btnbuysong\"><i>Mua</i></button>";
								}
								else{
									echo "<a><img src=\"images/checked1.png\"></a>";
								}
							}
							else{
								echo "<a href=\"login.php\" title=\"Mời bạn đăng nhập\" class=\"dn-1\">Đăng nhập</a>";
							}
							echo "	</td>
								</tr>";
							$i++;
						}
					?>
				</tbody>
			</table>
            
			<?php 
                	if(isset($_POST['upfilebtn'])){
                		echo "<div class=\"reponse-" . ($signdat!="" ? "success" : "failure") . "\">" . ($signdat!="" ? $signdat : "Hãy chọn thư mục nhạc cần xem chữ kí!") . "</div>";
        			}
        		?>
			<form class="getsignature" action="" method="post" enctype="multipart/form-data" style="display: none;">				
                <input id="upfile-input-file" name="upfile" type="file" accept='audio/wav' style="display: none;"/>
                <label for="upfile-input-file" class="btn btn-primary"><i class="fa fa-search"></i> Chọn file nhạc</label>
                <label id="upfile-file-name" style="display: none;"></label>
                <button class="btn btn-primary" name="upfilebtn"><i class="fa fa-upload"></i> Xem chữ kí</button>
               
			</form>

			<form class="uploadnewsong" action="" method="post" enctype="multipart/form-data" style="display: none;">
				<input id="upfile-song" name="upfilesong" type="text" placeholder="Tên bài hát" />
				<input id="upfile-singer" name="upfilesinger" type="text" placeholder="Tên ca sĩ" />
                <input id="upfile-input-file-1" name="upfile1" type="file" accept='audio/wav' style="display: none;"/>
                <label for="upfile-input-file-1" class="btn btn-success"><i class="fa fa-search"></i> Chọn từ máy tính</label>
                
                <button class="btn btn-success" name="upfilebtn1"><i class="fa fa-upload"></i> Tải lên</button>
                <label id="upfile-file-name-1" style="display: none;"></label>
			</form>
		</div>
        
         
         <div class="footer">
         		<p><div id="thoigian">
            <div id="date">
		<script>
			var d= new Date();
			var ngay = ["Chủ nhật", "Thứ 2", "Thứ 3","Thứ 4","Thứ 5","Thứ 6","Thứ 7"];
			var thang = ["1","2","3","4","5","6","7","8","9","10","11","12"];
			document.getElementById("date").innerHTML = ngay[d.getDay()] + " ngày " + d.getDate() +" tháng "+ thang[d.getMonth()] +" năm " +d.getFullYear();
		</script></div>
			<div id="time">
			<script>
				function dongho(){
					var time = new Date();
					var gio = time.getHours();
					var phut = time.getMinutes();
					var giay = time.getSeconds();
					if(gio < 10) gio = "0" + gio;
					if(phut < 10) phut  = "0" + phut;
					if(giay < 10) giay = "0" + giay;
					document.getElementById("time").innerHTML = gio + ":" + phut +":" + giay;
					setTimeout("dongho()",1000);

				}
				dongho();
			</script>
			</div>	</div></p>
 				 <p>AudioWaterMarkingDemo-N14DCAT094</p>
			</div>
              
   
        
         
</body>
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
	<script type="text/javascript" src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
	<script type="text/javascript" src="wow.min.js"></script>
	<script type="text/javascript">
	    new WOW().init();

	    if ((window.location.href).split(/[#]+/).pop() == "getsignature"){
	    	$("#buy-more-song").removeClass("active");
    		$(this).addClass("active");
    		$("#upload-new-song").removeClass("active");
    		$('.songstable').css("display", "none");
    		$('.getsignature').css("display", "");
    		$('.uploadnewsong').css("display", "none");
	    }

    	$("#buy-more-song").click(function(){
    		$(this).addClass("active");
    		$("#get-signature").removeClass("active");
    		$("#upload-new-song").removeClass("active");
    		$('.songstable').css("display", "");
    		$('.getsignature').css("display", "none");
    		$('.uploadnewsong').css("display", "none");
    	});

    	$("#get-signature").click(function(){
    		$("#buy-more-song").removeClass("active");
    		$(this).addClass("active");
    		$("#upload-new-song").removeClass("active");
    		$('.songstable').css("display", "none");
    		$('.getsignature').css("display", "");
    		$('.uploadnewsong').css("display", "none");
			$('.reponse-failure').css("display", "");
			$('.reponse-success').css("display", "");
    	});

    	$("#upload-new-song").click(function(){
    		$("#buy-more-song").removeClass("active");
    		$("#get-signature").removeClass("active");
    		$(this).addClass("active");
    		$('.songstable').css("display", "none");
    		$('.getsignature').css("display", "none");
    		$('.uploadnewsong').css("display", "");
			$('.reponse-failure').css("display", "none");
			$('.reponse-success').css("display", "none");
    	});

    	$(".btnbuysong").click(function(){
	    	$("*").css("cursor", "wait");
	    	var buysongid = $(this).attr("id");
	    	$.ajax({
				url: "buysong.php",
				type: "POST",
				data: { buysongid : buysongid },
				success : function(response){
					$("*").css("cursor", "default");
					if (response == "buy success"){
					  alert("Mua bài hát thành công!");
					  window.location="";
					}
					else if (response == "buy failure"){
					  alert("Mua bài hát thất bại!");
					  window.location="";
					}
				}
			});
    	});

    	$('#upfile-input-file').change(function(){
			var filename = $('#upfile-input-file').val().split('\\').pop();
			if (filename != ""){
				$('#upfile-file-name').attr('style','display: inline-block;');
				fnlength = 60;
				if (filename.length > fnlength)
					filename = filename.substr(0, fnlength/2) + "..." + filename.substr(filename.length - fnlength/2)
				$('#upfile-file-name').html(filename);
			}
			else
				$('#upfile-file-name').hide();
		});

		$('#upfile-input-file-1').change(function(){
			var filename = $('#upfile-input-file-1').val().split('\\').pop();
			if (filename != ""){
				$('#upfile-file-name-1').attr('style','display: inline-block;');
				fnlength = 60;
				if (filename.length > fnlength)
					filename = filename.substr(0, fnlength/2) + "..." + filename.substr(filename.length - fnlength/2)
				$('#upfile-file-name-1').html(filename);
			}
			else
				$('#upfile-file-name-1').hide();
		});
    </script>
</html>
