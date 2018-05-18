#Demo AudioWatermarking

+++Sử dụng file nhạc (.wav)+++++
----------------------------------------------------------------------------
+++Sử dụng PHPv7 . Cấu hình file "php.ini" trong Apache:
	upload_max_filesize = 60M
	post_max_size=60M
	(Để có thể upload file nhạc có size lớn, file .WAV nặng hơn file mp3 khá nhiều , 1 file nhạc mp3 11Mb khi convert qua WAV tăng lên thành 44Mb)
----------------------------------------------------------------------------
+++Tài khoản admin:
	id: admin
	pass: 9876543210
	-----Tài khoản admin để có thể upload nhạc cho user mua nhạc----
-----------------------------------------------------------------------------
+++Tài khoản user:
	id: thanhnam
	pass: 123456789
	-----Mua nhạc, kiểm tra chữ kí------
+++Chú ý chỉnh đúng thời gian laptop để có thể sử dụng googleAPIs
-----------------------------------------------------------------------------