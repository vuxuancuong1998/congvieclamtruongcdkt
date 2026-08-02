<?php
/**
 * Project: thuvien.
 * File: tourController.php.
 * Author: Ken Zaki
 * Email: kenzaki@xiao.vn
 * Create Date: 09:54 - 07/10/2016
 * Website: www.xiao.vn
 */
Class apiController extends baseController
{ 
	private function ensureAdminPermissionTables()
	{
		global $db;
		$db->query("CREATE TABLE IF NOT EXISTS hicrm_admin_menu_permissions (
			id int(11) NOT NULL AUTO_INCREMENT,
			permission_key varchar(100) NOT NULL,
			permission_name varchar(255) NOT NULL,
			parent_key varchar(100) DEFAULT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			permission_status int(11) NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_permission_key (permission_key)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$db->query("CREATE TABLE IF NOT EXISTS hicrm_user_group_permissions (
			id int(11) NOT NULL AUTO_INCREMENT,
			group_id int(11) NOT NULL,
			permission_id int(11) NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_group_permission (group_id, permission_id),
			KEY idx_group_id (group_id),
			KEY idx_permission_id (permission_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}
	private function adminTwoFactorConfigEnabled()
	{
		global $db;
		$db->query("SELECT config_value FROM hicrm_configs WHERE config_key IN ('P2A', 'PA2') ORDER BY FIELD(config_key, 'P2A', 'PA2') LIMIT 1");
		if(!$db->num_row()){
			return false;
		}
		$config = $db->fetch_object(true);
		return intval(isset($config->config_value) ? $config->config_value : 0) === 1;
	}
	private function adminSetLoggedInSession($user)
	{
		$_SESSION['user']['id'] = $user->id;
		$_SESSION['user']['email'] = $user->user_email;
		$_SESSION['user']['full_name'] = $user->full_name;
		$_SESSION['user']['group'] = $user->user_group;
		$_SESSION['LoggedIn'] = 1;
	}
	private function adminClearTwoFactorSession()
	{
		if(isset($_SESSION['admin_login_2fa'])){
			unset($_SESSION['admin_login_2fa']);
		}
	}
	private function adminStartTwoFactorSession($user)
	{
		$email = trim((string)$user->user_email);
		if($email === ''){
			return array('status' => false, 'message' => 'Tài khoản quản trị chưa có email để nhận mã xác thực.');
		}

		try {
			$code = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
		} catch (Exception $e) {
			$code = str_pad((string)mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
		}

		$this->adminClearTwoFactorSession();
		$_SESSION['admin_login_2fa'] = array(
			'user_id' => (int)$user->id,
			'user_group' => (int)$user->user_group,
			'user_email' => $email,
			'full_name' => trim((string)$user->full_name),
			'code_hash' => md5($code),
			'expires_at' => time() + 120
		);

		$sent = $this->mail->sendAdminTwoFactorCode(
			trim((string)$user->full_name) !== '' ? trim((string)$user->full_name) : $email,
			$email,
			$code,
			'Mã xác thực đăng nhập quản trị'
		);

		if(!$sent){
			$this->adminClearTwoFactorSession();
			return array('status' => false, 'message' => 'Không thể gửi email xác thực. Vui lòng kiểm tra cấu hình email hệ thống.');
		}

		return array('status' => true);
	}
	private function adminFetchActiveUserById($userId)
	{
		global $db;
		$db->query("SELECT u.*
			FROM hicrm_users u
			INNER JOIN hicrm_user_groups g ON u.user_group = g.id AND g.group_status NOT IN(99)
			WHERE u.id = '".intval($userId)."'
				AND u.user_status = 1
			LIMIT 1");
		return $db->num_row() ? $db->fetch_object(true) : null;
	}
	private function adminGetTwoFactorRemainingSeconds()
	{
		$pending = isset($_SESSION['admin_login_2fa']) && is_array($_SESSION['admin_login_2fa']) ? $_SESSION['admin_login_2fa'] : array();
		if(empty($pending) || !isset($pending['expires_at'])){
			return 0;
		}
		return max(0, intval($pending['expires_at']) - time());
	}
	private function frontendLoginReturnUrl($user)
	{
		$group = isset($user->user_group) ? intval($user->user_group) : 0;
		if($group === 1){
			return XC_URL.'/admin';
		}
		if($group === 2){
			return XC_URL.'/quan-ly-nha-tuyen-dung.html';
		}
		if($group === 3 || $group === 4){
			return XC_URL.'/quan-ly-ho-so-ung-vien.html';
		}
		return XC_URL;
	}
	private function frontendCurrentBaseUrl()
	{
		$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
		$scheme = $isHttps ? 'https' : 'http';
		$host = isset($_SERVER['HTTP_HOST']) && trim((string)$_SERVER['HTTP_HOST']) !== '' ? trim((string)$_SERVER['HTTP_HOST']) : parse_url(XC_URL, PHP_URL_HOST);
		$scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']) : '';
		$basePath = rtrim(dirname($scriptName), '/');
		if($basePath === '/' || $basePath === '.'){
			$basePath = '';
		}
		return $scheme.'://'.$host.$basePath;
	}
	private function frontendUserRequiresVerification($user)
	{
		return isset($user->user_is_verified) && intval($user->user_is_verified) !== 1;
	}
	private function frontendStorePendingVerification($user)
	{
		$_SESSION['frontend_pending_verification'] = array(
			'user_id' => isset($user->id) ? intval($user->id) : 0,
			'email' => isset($user->user_email) ? trim((string)$user->user_email) : '',
			'full_name' => isset($user->full_name) ? trim((string)$user->full_name) : '',
			'user_group' => isset($user->user_group) ? intval($user->user_group) : 0,
			'created_at' => time()
		);
	}
	private function frontendClearPendingVerification()
	{
		if(isset($_SESSION['frontend_pending_verification'])){
			unset($_SESSION['frontend_pending_verification']);
		}
	}
	private function adminApiAllowedMenuKeys()
	{
		global $db;
		if(!isset($_SESSION['user']['id']) || intval($_SESSION['user']['id']) <= 0){ return array(); }
		$db->query("SELECT user_group FROM hicrm_users WHERE id = '".intval($_SESSION['user']['id'])."' AND user_status = 1 LIMIT 1");
		$user = $db->fetch_object(true);
		if(!$user){ return array(); }
		if(intval($user->user_group) === 1){ return array('*'); }
		$this->ensureAdminPermissionTables();
		$db->query("SELECT p.permission_key FROM hicrm_user_group_permissions gp
			INNER JOIN hicrm_admin_menu_permissions p ON p.id = gp.permission_id
			WHERE gp.group_id = '".intval($user->user_group)."' AND p.permission_status = 1");
		$rows = $db->fetch_object();
		$keys = array();
		if(is_array($rows)){ foreach($rows as $row){ $keys[] = $row->permission_key; } }
		return $keys;
	}
	private function requireAdminApiPermission($permission_key, $verify_csrf = true)
	{
		$keys = $this->adminApiAllowedMenuKeys();
		if(!in_array('*', $keys, true) && !in_array($permission_key, $keys, true)){
			http_response_code(403);
			echo json_encode(array('status' => 403, 'message' => 'Bạn không có quyền thực hiện thao tác này.'));
			return false;
		}
		if($verify_csrf){
			$token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
			$session_token = isset($_SESSION['admin_csrf_token']) ? (string)$_SESSION['admin_csrf_token'] : '';
			if($token === '' || $session_token === '' || !hash_equals($session_token, $token)){
				http_response_code(419);
				echo json_encode(array('status' => 419, 'message' => 'Phiên thao tác đã hết hạn. Vui lòng tải lại trang.'));
				return false;
			}
		}
		return true;
	}
	private function adminAccountHasAccess($group_id)
	{
		global $db;
		if(intval($group_id) === 1){ return true; }
		$this->ensureAdminPermissionTables();
		$db->query("SELECT gp.id FROM hicrm_user_group_permissions gp
			INNER JOIN hicrm_admin_menu_permissions p ON p.id = gp.permission_id AND p.permission_status = 1
			WHERE gp.group_id = '".intval($group_id)."' LIMIT 1");
		return $db->num_row() > 0;
	}
	private function currentAdminIsSuperAdmin()
	{
		global $db;
		if(!isset($_SESSION['user']['id'])){ return false; }
		$db->query("SELECT user_group FROM hicrm_users WHERE id = '".intval($_SESSION['user']['id'])."' AND user_status = 1 LIMIT 1");
		$user = $db->fetch_object(true);
		return $user && intval($user->user_group) === 1;
	}
	private function frontendTableExists($table_name)
	{
		global $db;
		$table_name = $db->escapestring($table_name);
		$db->query("SHOW TABLES LIKE '".$table_name."'");
		return $db->num_row() > 0;
	}
	private function frontendTableHasColumn($table_name, $column_name)
	{
		global $db;
		$table_name = $db->escapestring($table_name);
		$column_name = $db->escapestring($column_name);
		$db->query("SHOW COLUMNS FROM `".$table_name."` LIKE '".$column_name."'");
		return $db->num_row() > 0;
	}
	private function ensureJobApplicationsTable()
	{
		global $db;
		if(!$this->frontendTableExists('hicrm_job_applications')){
			$db->query("CREATE TABLE IF NOT EXISTS hicrm_job_applications (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				candidate_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned DEFAULT NULL,
				job_post_id bigint(20) unsigned NOT NULL,
				employer_id bigint(20) unsigned DEFAULT NULL,
				status varchar(50) NOT NULL DEFAULT 'submitted',
				applied_at datetime DEFAULT CURRENT_TIMESTAMP,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY uniq_candidate_job (candidate_id, job_post_id),
				KEY idx_candidate_id (candidate_id),
				KEY idx_job_post_id (job_post_id),
				KEY idx_employer_id (employer_id),
				KEY idx_status (status)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
			return;
		}

		if(!$this->frontendTableHasColumn('hicrm_job_applications', 'user_id')){
			$db->query("ALTER TABLE hicrm_job_applications ADD COLUMN user_id bigint(20) unsigned DEFAULT NULL AFTER candidate_id");
		}
		if(!$this->frontendTableHasColumn('hicrm_job_applications', 'employer_id')){
			$db->query("ALTER TABLE hicrm_job_applications ADD COLUMN employer_id bigint(20) unsigned DEFAULT NULL AFTER job_post_id");
		}
		if(!$this->frontendTableHasColumn('hicrm_job_applications', 'created_at')){
			$db->query("ALTER TABLE hicrm_job_applications ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP AFTER applied_at");
		}
	}
	private function frontendCandidateByUserId($userId)
	{
		global $db;
		$db->query("SELECT * FROM hicrm_candidates WHERE user_id = '".intval($userId)."' LIMIT 1");
		return $db->num_row() > 0 ? $db->fetch_object(true) : null;
	}
	private function frontendSendVerificationEmail($user, $forceRenewToken = true)
	{
		global $db;
		$email = isset($user->user_email) ? trim((string)$user->user_email) : '';
		if($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)){
			return array('status' => false, 'message' => 'Tài khoản chưa có email hợp lệ để nhận liên kết xác thực.');
		}

		$token = isset($user->user_email_verify_token) ? trim((string)$user->user_email_verify_token) : '';
		$expiresAt = isset($user->user_email_verified_at) ? trim((string)$user->user_email_verified_at) : '';
		$shouldRenew = $forceRenewToken || $token === '' || $expiresAt === '' || strtotime($expiresAt) <= time();

		if($shouldRenew){
			try {
				$token = bin2hex(random_bytes(32));
			} catch (Exception $e) {
				$token = md5(uniqid((string)mt_rand(), true)).md5((string)microtime(true));
			}
			$expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
			$db->query("UPDATE hicrm_users SET user_email_verify_token = '".$db->escapestring($token)."', user_email_verified_at = '".$db->escapestring($expiresAt)."' WHERE id = '".intval($user->id)."' LIMIT 1");
		}

		$displayName = isset($user->full_name) && trim((string)$user->full_name) !== '' ? trim((string)$user->full_name) : $email;
		$sent = $this->mail->sendVerifyEmail($displayName, $email, $token, 'Xác thực tài khoản đăng nhập hệ thống Cổng thông tin việc làm Trường Cao đẳng Kon Tum');

		if(!$sent){
			return array('status' => false, 'message' => 'Không thể gửi email xác thực. Vui lòng kiểm tra cấu hình email hệ thống.');
		}

		return array(
			'status' => true,
			'message' => 'Hệ thống đã gửi liên kết xác thực đến email '.$this->maskEmail($email).'.',
			'email' => $email
		);
	}

    public function index()
    {
		
    }
	private function homeApiJson($payload)
	{
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($payload, JSON_UNESCAPED_UNICODE);
		exit();
	}
	private function homeApiH($value)
	{
		return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	}
	private function homeApiNormalize($value)
	{
		$value = mb_strtolower((string)$value, 'UTF-8');
		$from = array('à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ','è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ','ì','í','ị','ỉ','ĩ','ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ','ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ','ỳ','ý','ỵ','ỷ','ỹ','đ');
		$to = array('a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','e','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y','d');
		return str_replace($from, $to, $value);
	}
	private function homeApiSalaryKey($text)
	{
		$text = $this->homeApiNormalize($text);
		if(strpos($text, '1') !== false && strpos($text, '3') !== false) return '1-3';
		if(strpos($text, '3') !== false && strpos($text, '5') !== false) return '3-5';
		if(strpos($text, '5') !== false && strpos($text, '7') !== false) return '5-7';
		if(strpos($text, '7') !== false && strpos($text, '10') !== false) return '7-10';
		if(strpos($text, '10') !== false && strpos($text, '15') !== false) return '10-15';
		if(strpos($text, '15') !== false && strpos($text, '20') !== false) return '15-20';
		if(strpos($text, '20') !== false) return '20+';
		return '';
	}
	private function homeApiLocationKey($text)
	{
		$text = $this->homeApiNormalize($text);
		if(strpos($text, 'ha noi') !== false) return 'hanoi';
		if(strpos($text, 'ho chi minh') !== false || strpos($text, 'hcm') !== false) return 'tphcm';
		if(strpos($text, 'da nang') !== false) return 'danang';
		if(strpos($text, 'binh duong') !== false) return 'binhduong';
		if(strpos($text, 'can tho') !== false) return 'cantho';
		return '';
	}
	private function homeApiIndustryKey($text)
	{
		$text = $this->homeApiNormalize($text);
		if(strpos($text, 'tai chinh') !== false || strpos($text, 'ngan hang') !== false) return 'finance';
		if(strpos($text, 'marketing') !== false) return 'marketing';
		if(strpos($text, 'ke toan') !== false) return 'accounting';
		if(strpos($text, 'logistics') !== false || strpos($text, 'kho') !== false) return 'logistics';
		if(strpos($text, 'cong nghe') !== false || strpos($text, 'lap trinh') !== false || strpos($text, 'tester') !== false || strpos($text, 'php') !== false || strpos($text, 'java') !== false) return 'it';
		if(strpos($text, 'nhan su') !== false || strpos($text, 'hr') !== false) return 'hr';
		if(strpos($text, 'cham soc') !== false || strpos($text, 'dich vu') !== false) return 'service';
		if(strpos($text, 'kinh doanh') !== false || strpos($text, 'ban hang') !== false || strpos($text, 'sales') !== false) return 'sales';
		return '';
	}
	private function homeApiWorkTypeLabel($value)
	{
		$labels = array('full_time' => 'Full-time', 'part_time' => 'Part-time', 'remote' => 'Remote', 'hybrid' => 'Hybrid', 'internship' => 'Thực tập', 'contract' => 'Hợp đồng');
		$value = trim((string)$value);
		return isset($labels[$value]) ? $labels[$value] : ($value !== '' ? $value : 'Đang tuyển');
	}
	private function homeApiExperienceText($value)
	{
		$value = trim((string)$value);
		return ($value === '' || $value === '0') ? 'Chưa yêu cầu KN' : $value.' năm KN';
	}
	private function homeApiDateText($value)
	{
		if(!$value){ return 'Mới đăng'; }
		$time = strtotime($value);
		if(!$time){ return 'Mới đăng'; }
		$seconds = max(0, time() - $time);
		$minutes = floor($seconds / 60);
		if($minutes < 1){ return 'Vừa xong'; }
		if($minutes < 60){ return $minutes.'p trước'; }
		$hours = floor($minutes / 60);
		if($hours < 24){ return $hours.'h trước'; }
		return floor($hours / 24).' ngày trước';
	}
	private function homeApiDeadlineText($value)
	{
		$time = $value ? strtotime($value) : false;
		return $time ? date('d/m/Y', $time) : 'Đang cập nhật';
	}
	private function homeApiJobCard($job, $extraClass = '', $includeDeadline = false)
	{
		$title = isset($job->title) ? $job->title : 'Việc làm đang tuyển';
		$company = !empty($job->company_name) ? $job->company_name : 'Nhà tuyển dụng';
		$salary = !empty($job->salary_name) ? $job->salary_name : 'Thỏa thuận';
		$location = !empty($job->province_name) ? $job->province_name : 'Toàn quốc';
		$industry = !empty($job->job_category_name) ? $job->job_category_name : '';
		$href = general::getInstance()->permalink((int)$job->id, 'job_post');
		$workType = $this->homeApiWorkTypeLabel($job->work_type ?? '');
		
		$postType = isset($job->job_post_type) ? $job->job_post_type : 'normal';
		$titleClass = $postType === 'hot' ? ' job-title-hot' : ($postType === 'urgent' ? ' job-title-urgent' : '');
		$classes = trim('job-card job-card-'.$postType.' '.$extraClass);
		$initials = mb_strtoupper(mb_substr($company, 0, 1, 'UTF-8'), 'UTF-8');
		$logo_url = !empty($job->logo_url) ? '<img src="'.XC_URL.'/'.$this->homeApiH($job->logo_url).'" alt="'.$this->homeApiH($company).'" />' : $initials ;
		$html = '<a href="'.$this->homeApiH($href).'" class="'.$this->homeApiH($classes).'" data-salary="'.$this->homeApiH($this->homeApiSalaryKey($salary)).'" data-location="'.$this->homeApiH($this->homeApiLocationKey($location)).'" data-experience="'.$this->homeApiH($job->experience_years ?? '').'" data-industry="'.$this->homeApiH($this->homeApiIndustryKey($industry.' '.$title)).'">';
		$html .= '<div class="job-card-header">
		<div class="company-logo" style="background:#eef6ff;color:#0d4e96">'.$logo_url.'</div>
		
		<div><div class="job-title'.$titleClass.'">'.$this->homeApiH($title).'</div><div class="company-name"><i class="ti ti-building"></i> '.$this->homeApiH($company).'</div></div></div>';
		$html .= '<div class="job-card-tags"><span class="tag tag-salary">'.$this->homeApiH($salary).'</span><span class="tag tag-location"><i class="ti ti-map-pin" style="font-size:10px"></i> '.$this->homeApiH($location).'</span><span class="tag tag-type">'.$this->homeApiH($workType).'</span><span class="tag tag-experience">'.$this->homeApiH($this->homeApiExperienceText($job->experience_years ?? '')).'</span>';
		if($includeDeadline){ $html .= '<span class="tag tag-deadline"><i class="ti ti-calendar-event" style="font-size:10px"></i> Hạn nộp: '.$this->homeApiH($this->homeApiDeadlineText($job->deadline ?? '')).'</span>'; }
		$html .= '</div><div class="job-card-footer"><span class="job-date"><i class="ti ti-clock"></i> '.$this->homeApiH($this->homeApiDateText($job->published_at ?? $job->created_at ?? '')).'</span></div></a>';
		return $html;
	}
	private function homeApiFilterWhere($filterType, $filterValue)
	{
		global $db;
		$filterType = trim((string)$filterType);
		$filterValue = trim((string)$filterValue);
		if($filterType === '' || $filterType === 'all' || $filterValue === '' || $filterValue === 'all'){
			return '';
		}
		switch($filterType){
			case 'location':
				if(preg_match('/^loc_(\d+)$/', $filterValue, $matches)){
					return " AND p.province_id = '".intval($matches[1])."'";
				}
				$map = array('hanoi' => 'ha noi', 'tphcm' => 'ho chi minh', 'danang' => 'da nang', 'binhduong' => 'binh duong', 'cantho' => 'can tho');
				if(!isset($map[$filterValue])){ return ''; }
				return " AND LOWER(CONVERT(pr.province_name USING utf8mb4)) LIKE '%".$db->escapestring($map[$filterValue])."%'";
			case 'salary':
				if(preg_match('/^sal_(\d+)$/', $filterValue, $matches)){
					return " AND p.salary_id = '".intval($matches[1])."'";
				}
				$map = array(
					'1-3' => array(1, 3),
					'3-5' => array(3, 5),
					'5-7' => array(5, 7),
					'7-10' => array(7, 10),
					'10-15' => array(10, 15),
					'15-20' => array(15, 20)
				);
				if($filterValue === '20+'){ return " AND LOWER(CONVERT(s.salary_name USING utf8mb4)) LIKE '%20%'"; }
				if(!isset($map[$filterValue])){ return ''; }
				return " AND LOWER(CONVERT(s.salary_name USING utf8mb4)) LIKE '%".$map[$filterValue][0]."%' AND LOWER(CONVERT(s.salary_name USING utf8mb4)) LIKE '%".$map[$filterValue][1]."%'";
			case 'industry':
				if(preg_match('/^cat_(\d+)$/', $filterValue, $matches)){
					return " AND p.job_category_id = '".intval($matches[1])."'";
				}
				$map = array(
					'finance' => array('tai chinh', 'ngan hang'),
					'sales' => array('kinh doanh', 'ban hang', 'sales'),
					'it' => array('cong nghe', 'lap trinh', 'php', 'java', 'tester'),
					'marketing' => array('marketing'),
					'hr' => array('nhan su', 'hr'),
					'accounting' => array('ke toan'),
					'logistics' => array('logistics', 'kho'),
					'service' => array('cham soc', 'dich vu')
				);
				if(!isset($map[$filterValue])){ return ''; }
				$likes = array();
				foreach($map[$filterValue] as $keyword){
					$kw = $db->escapestring($keyword);
					$likes[] = "LOWER(CONVERT(CONCAT(COALESCE(c.job_category_name, ''), ' ', COALESCE(p.title, '')) USING utf8mb4)) LIKE '%".$kw."%'";
				}
				return " AND (".implode(' OR ', $likes).")";
			case 'experience':
				if(preg_match('/^exp_(\d+)$/', $filterValue, $matches)){
					$experience = intval($matches[1]);
					$normalizedExperience = "CASE
						WHEN COALESCE(NULLIF(TRIM(p.experience_years), ''), '0') REGEXP '^[0-9]+$'
							THEN CAST(COALESCE(NULLIF(TRIM(p.experience_years), ''), '0') AS UNSIGNED)
						ELSE 0
					END";
					return " AND ".$normalizedExperience." = '".$experience."'";
				}
				if($filterValue === 'none'){ return " AND (COALESCE(p.experience_years, '') = '' OR p.experience_years = '0')"; }
				if($filterValue === '1-2'){ return " AND CAST(COALESCE(NULLIF(p.experience_years, ''), '0') AS UNSIGNED) BETWEEN 1 AND 2"; }
				if($filterValue === '3-5'){ return " AND CAST(COALESCE(NULLIF(p.experience_years, ''), '0') AS UNSIGNED) BETWEEN 3 AND 5"; }
				if($filterValue === '5+'){ return " AND CAST(COALESCE(NULLIF(p.experience_years, ''), '0') AS UNSIGNED) >= 5"; }
				return '';
		}
		return '';
	}
	public function homeFeaturedJobs()
	{
		global $db;
		$page = max(1, intval(isset($_GET['page']) ? $_GET['page'] : 1));
		$perPage = 15;
		$where = " WHERE p.status = 'published' AND COALESCE(p.published_at, p.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
		$where .= $this->homeApiFilterWhere($_GET['filter_type'] ?? 'all', $_GET['filter_value'] ?? 'all');
		$from = " FROM hicrm_job_posts p LEFT JOIN hicrm_employers e ON e.id = p.employer_id LEFT JOIN hicrm_job_categories c ON c.id = p.job_category_id LEFT JOIN hicrm_provinces pr ON pr.id = p.province_id LEFT JOIN hicrm_salary s ON s.id = p.salary_id";
		$db->query("SELECT COUNT(p.id) AS total".$from.$where);
		$total = intval($db->fetch_object(true)->total);
		$totalPages = max(1, ceil($total / $perPage));
		if($page > $totalPages){ $page = $totalPages; }
		$offset = ($page - 1) * $perPage;
		$db->query("SELECT p.*, e.company_name, e.logo_url, c.job_category_name, pr.province_name, s.salary_name".$from.$where." ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.deadline IS NULL ASC, p.deadline ASC, p.id DESC LIMIT ".$offset.",".$perPage);
		$jobs = $db->fetch_object();
		$html = '';
		foreach((array)$jobs as $job){ $html .= $this->homeApiJobCard($job, 'latest-job-card', true); }
		$this->homeApiJson(array('status' => 200, 'page' => $page, 'total_pages' => $totalPages, 'html' => $html));
	}
	public function homeProvinceJobs()
	{
		global $db;
		$page = max(1, intval(isset($_GET['page']) ? $_GET['page'] : 1));
		$perPage = 6;
		$where = " WHERE p.status = 'published' AND p.province_id IS NOT NULL AND p.province_id = 22";
		$from = " FROM hicrm_job_posts p LEFT JOIN hicrm_employers e ON e.id = p.employer_id LEFT JOIN hicrm_job_categories c ON c.id = p.job_category_id LEFT JOIN hicrm_provinces pr ON pr.id = p.province_id LEFT JOIN hicrm_salary s ON s.id = p.salary_id";
		$db->query("SELECT COUNT(p.id) AS total".$from.$where);
		$total = intval($db->fetch_object(true)->total);
		$totalPages = max(1, ceil($total / $perPage));
		if($page > $totalPages){ $page = $totalPages; }
		$offset = ($page - 1) * $perPage;
		$db->query("SELECT p.*, e.company_name, e.logo_url, c.job_category_name, pr.province_name, s.salary_name".$from.$where." ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.deadline IS NULL ASC, p.deadline ASC, p.id DESC LIMIT ".$offset.",".$perPage);
		$jobs = $db->fetch_object();
		$html = '';
		foreach((array)$jobs as $job){ $html .= $this->homeApiJobCard($job, 'province-job-card latest-job-card', true); }
		$this->homeApiJson(array('status' => 200, 'page' => $page, 'total_pages' => $totalPages, 'html' => $html));
	}
	public function homeUrgentJobs()
	{
		global $db;
		$page = max(1, intval(isset($_GET['page']) ? $_GET['page'] : 1));
		$perPage = 9;
		$where = " WHERE p.status = 'published' AND p.job_post_type IN ('urgent', 'hot') AND e.is_linked_school = 1";
		$where .= $this->homeApiFilterWhere($_GET['filter_type'] ?? 'all', $_GET['filter_value'] ?? 'all');
		$from = " FROM hicrm_job_posts p INNER JOIN hicrm_employers e ON e.id = p.employer_id LEFT JOIN hicrm_job_categories c ON c.id = p.job_category_id LEFT JOIN hicrm_provinces pr ON pr.id = p.province_id LEFT JOIN hicrm_salary s ON s.id = p.salary_id";
		$db->query("SELECT COUNT(p.id) AS total".$from.$where);
		$total = intval($db->fetch_object(true)->total);
		$totalPages = max(1, ceil($total / $perPage));
		if($page > $totalPages){ $page = $totalPages; }
		$offset = ($page - 1) * $perPage;
		$db->query("SELECT p.*, e.company_name, e.logo_url, c.job_category_name, pr.province_name, s.salary_name".$from.$where." ORDER BY p.published_at DESC, p.deadline IS NULL ASC, p.deadline ASC, p.created_at DESC, p.id DESC LIMIT ".$offset.",".$perPage);
		$jobs = $db->fetch_object();
		$html = '';
		foreach((array)$jobs as $job){ $html .= $this->homeApiJobCard($job, 'urgent-job-card', true); }
		$this->homeApiJson(array('status' => 200, 'page' => $page, 'total_pages' => $totalPages, 'html' => $html));
	}
	public function homeFeaturedCandidates()
	{
		global $db;
		$page    = max(1, intval(isset($_GET['page']) ? $_GET['page'] : 1));
		$perPage = 12;
		// Đếm tổng ứng viên để tính total_pages động
		$db->query("SELECT COUNT(ca.id) AS total
			FROM hicrm_candidates ca
			LEFT JOIN hicrm_users u ON u.id = ca.user_id
			WHERE ca.status = 3 AND ca.is_seeking = 1
			  AND (u.id IS NULL OR u.user_status = 1)");
		$total      = intval($db->fetch_object(true)->total);
		$totalPages = max(1, ceil($total / $perPage));
		if($page > $totalPages){ $page = $totalPages; }
		$offset = ($page - 1) * $perPage;
		$db->query("SELECT ca.*, u.user_email, u.user_phone,
				jc.job_category_name,
				desired_pr.province_name AS desired_province_name,
				sal.salary_name
			FROM hicrm_candidates ca
			LEFT JOIN hicrm_users u ON u.id = ca.user_id
			LEFT JOIN hicrm_job_categories jc ON jc.id = ca.major
			LEFT JOIN hicrm_provinces desired_pr ON desired_pr.id = ca.desired_province_id
			LEFT JOIN hicrm_salary sal ON sal.id = ca.desired_salary
			WHERE ca.status = 3 AND ca.is_seeking = 1
			  AND (u.id IS NULL OR u.user_status = 1)
			ORDER BY ca.updated_at DESC, ca.id DESC
			LIMIT ".intval($offset).",".intval($perPage));
		$candidates = $db->fetch_object();
		$html = '';
		foreach((array)$candidates as $c){ $html .= $this->homeCandidateCard($c); }
		$this->homeApiJson(array('status' => 200, 'page' => $page, 'total_pages' => $totalPages, 'html' => $html));
	}
	private function homeCandidateCard($candidate)
	{
		$id    = (int)(isset($candidate->id) ? $candidate->id : 0);
		$name  = trim((string)(isset($candidate->full_name) ? $candidate->full_name : (isset($candidate->candidate_name) ? $candidate->candidate_name : '')));
		if($name === ''){ $name = 'Ứng viên nổi bật'; }
		$major = trim((string)(isset($candidate->job_category_name) ? $candidate->job_category_name : (isset($candidate->desired_position) ? $candidate->desired_position : '')));
		if($major === ''){ $major = 'Ứng viên đang tìm việc'; }
		$url   = general::getInstance()->permalink($id, 'candidate_profile');
		// Ngày sinh
		$raw   = isset($candidate->date_of_birth) ? $candidate->date_of_birth : (isset($candidate->birthday) ? $candidate->birthday : (isset($candidate->dob) ? $candidate->dob : ''));
		$time  = ($raw && $raw !== '') ? @strtotime((string)$raw) : false;
		$dob   = $time ? date('d/m/Y', $time) : 'Đang cập nhật';
		// Avatar
		$avatarRaw = trim((string)(isset($candidate->avatar_url) ? $candidate->avatar_url : (isset($candidate->user_avatar_url) ? $candidate->user_avatar_url : '')));
		if($avatarRaw !== '' && !preg_match('#^(https?:)?//#i', $avatarRaw) && strpos($avatarRaw, 'data:') !== 0){
			$avatarRaw = XC_URL.'/'.ltrim($avatarRaw, '/');
		}
		// Màu + viết tắt
		$palette  = array('#0d4e96','#1565c0','#2e7d32','#c62828','#6a1b9a','#00695c','#e65100','#1a237e','#00838f','#37474f');
		$color    = $palette[$id % count($palette)];
		$parts    = preg_split('/\s+/', $name);
		$letters  = '';
		foreach((array)$parts as $part){
			if($part !== ''){ $letters .= mb_substr($part, 0, 1, 'UTF-8'); }
			if(mb_strlen($letters, 'UTF-8') >= 2){ break; }
		}
		$initials = mb_strtoupper($letters !== '' ? $letters : mb_substr($name, 0, 2, 'UTF-8'), 'UTF-8');
		$H = function($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
		// Build HTML — giữ đúng cấu trúc .sv-card như template PHP
		$html  = '<a href="'.$H($url).'" class="sv-card">';
		$html .= '<div class="sv-avatar-wrap">';
		if($avatarRaw !== ''){
			$html .= '<img src="'.$H($avatarRaw).'" alt="'.$H($name).'" class="sv-avatar-photo" loading="lazy"'
			       . ' onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">';
			$html .= '<div class="sv-avatar-fallback" style="background:'.$H($color).';display:none">'.$H($initials).'</div>';
		} else {
			$html .= '<div class="sv-avatar-fallback" style="background:'.$H($color).'">'.$H($initials).'</div>';
		}
		$html .= '</div>';
		$html .= '<div class="sv-name" title="'.$H($name).'">'.$H($name).'</div>';
		$html .= '<div class="sv-dob"><i class="ti ti-calendar"></i> '.$H($dob).'</div>';
		$html .= '<div class="sv-major">'.$H($major).'</div>';
		$html .= '</a>';
		return $html;
	}
	public function getbalance()
	{
		$result = array();
		$result["status"] = 200;
		$result["balance"] = "20.000.000";
		echo json_encode($result);
	}
	public function applogin()
	{
		$result = array();
		$result["status"] = 200;
		$result["email"] = $_POST['email'];
		$result["fullname"] = "Sang Test";
		$result["uid"] = 1;
		$result["token"] = "21231231238821";
		echo json_encode($result);
	}
	//======================== user =================================//
	public function adduser(){
		if(!$this->requireAdminApiPermission('users', false)){ return; }
		global $db;
		
		// $user_fullname = $_POST['user_fullname'];
		$user_email = $db->escapestring($_POST['user_email']);
		$user_username = $db->escapestring($_POST['user_username']);
		$user_password = md5($db->escapestring($_POST['user_password']));
		// $user_password_confirm = md5($db->escapestring($_POST['user_password_confirm']));	
		// $user_avatar = $_POST['user_avatar'];
		// $user_group = $_POST['user_group'];
		// $user_dept = $_POST['user_dept'];
		$user_category = $_POST['user_category'];
		$user_status = 1;
		$user_commission = 0.00;
		$user_basic_salary = 0.00;
		$user_created_date = date("Y-m-d H:i:s");
		$result = array();
		// echo $user_fullname . 'aeqwe';
		$error = '';
			$db->query("SELECT * FROM hicrm_users WHERE user_email = '".$user_email."' ");
			$queryEmail = $db->fetch_object(true);
			if($db->num_row($queryEmail)){
				$error .= "Email đã tồn tại!"; 
				
			}
			$db->query("SELECT * FROM hicrm_users WHERE user_phone = '".$user_phone."' ");
			$queryPhone = $db->fetch_object(true);
			if($db->num_row($queryPhone)){
				$error .= "Số điện thoại đã tồn tại!"; 
				
			}
			$db->query("SELECT * FROM hicrm_users WHERE user_username = '".$user_username."' ");
			$queryUsername = $db->fetch_object(true);
			if($db->num_row($queryUsername)){
				$error .= "Tên đăng nhập đã tồn tại!"; 
				
			}
			// if($user_password != $user_password_confirm){
			// 	$result['status'] = 500;
			// 	$result['message'] = "Mật khẩu không trùng khớp!"; 
				
			// }elseif(isset($error) && $error != ''){
			// 	$result['status'] = 500;
			// 	$result['message'] = $error; 
			// }else{
			// $db->query("INSERT INTO hicrm_users(user_username, user_password, user_email, user_fullname, user_phone, user_group, user_dept, user_address, user_avatar, user_status, user_commission, user_basic_salary, user_register_time) VALUES ('".$user_username."','".$user_password."','".$user_email."','".$user_fullname."','".$user_phone."','".$user_group."','".$user_dept."','".$user_address."','".$user_avatar."','".$user_status."','".$user_commission."','".$user_basic_salary."','".$user_register_time."') "); 
			// $result['status'] = 200;
			// }
			$db->query("INSERT INTO hicrm_users(user_username, user_password, user_email, user_role, user_category, user_status, user_is_subscribed, user_created_date) VALUES
			 ('".$user_username."','".$user_password."','".$user_email."','0','".$user_category."','1','0','".$user_created_date."')"); 
			$result['status'] = 200;
		
		echo json_encode($result);
		
	}
	public function resetpassword(){
		global $db;
		if(!$this->requireAdminApiPermission('users')){ return; }
		$result = array();
		$id = isset($_POST['id']) ? $db->escapestring($_POST['id']) : '';
		if(empty($id)){
			$result['status'] = 400;
			$result['message'] = 'ID người dùng không hợp lệ';
			echo json_encode($result);
			return;
		}

		$db->query("SELECT * FROM hicrm_users WHERE id = '".$id."'");
		$user = $db->fetch_object(true);
		if(!$db->num_row()){
			$result['status'] = 404;
			$result['message'] = 'Người dùng không tồn tại';
			echo json_encode($result);
			return;
		}

		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$new_password = substr(str_shuffle(str_repeat($chars, 8)), 0, 8);
		$hashed_password = md5($db->escapestring($new_password));

		$db->query("UPDATE hicrm_users SET user_password = '".$hashed_password."' WHERE id = '".$id."'");

		$result['status'] = 200;
		$result['new_password'] = $new_password;
		echo json_encode($result);
	}	
	//insert user database
	public function userAction(){
		global $db;
		if(!$this->requireAdminApiPermission('users')){ return; }
		
		$full_name = $db->escapestring($_POST['full_name']);
		$email = $db->escapestring($_POST['user_email']);
		$password = md5($db->escapestring($_POST['user_password']));
		$user_group = isset($_POST['user_group']) ? intval($_POST['user_group']) : 0;
		$method = $_POST['method'];
		$user_created_date = date("d-m-Y");
		$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
		$result = array();
		if($user_group <= 0){
			$result['status'] = 400;
			$result['message'] = 'Vui lòng chọn nhóm quyền cho tài khoản';
			echo json_encode($result);
			return;
		}
		$db->query("SELECT id FROM hicrm_user_groups WHERE id = '".$user_group."' AND group_status NOT IN(99) LIMIT 1");
		if(!$db->num_row()){
			$result['status'] = 400;
			$result['message'] = 'Nhóm quyền không tồn tại hoặc đã bị xóa';
			echo json_encode($result);
			return;
		}
		if(intval($user->user_group) === 1 && !$this->currentAdminIsSuperAdmin()){
			$result['status'] = 403;
			$result['message'] = 'Không thể reset mật khẩu tài khoản Super Admin.';
			echo json_encode($result);
			return;
		}
		if(!$this->adminAccountHasAccess($user_group)){
			$result['status'] = 400;
			$result['message'] = 'Nhóm được chọn chưa có quyền truy cập Admin.';
			echo json_encode($result);
			return;
		}
		if($user_group === 1 && !$this->currentAdminIsSuperAdmin()){
			$result['status'] = 403;
			$result['message'] = 'Chỉ Super Admin mới được gán nhóm Super Admin.';
			echo json_encode($result);
			return;
		}
		
		if(isset($method) && $method == "add"){
			if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)){
				$result['status'] = 400;
				$result['message'] = 'Email không hợp lệ';
				echo json_encode($result);
				return;
			}
			$password_raw = trim($_POST['user_password']);
			if(empty($password_raw)){
				$result['status'] = 400;
				$result['message'] = 'Mật khẩu không được để trống';
				echo json_encode($result);
				return;
			}
			
			$password = md5($db->escapestring($password_raw));
			$db->query("SELECT * FROM hicrm_users WHERE user_email = '".$email."'");
			if($db->num_row()){
				$existing = $db->fetch_object(true);
				if($existing->user_email == $email){
					$result['status'] = 400;
					$result['message'] = 'Email đã tồn tại';
				}
				echo json_encode($result);
				return;
			}
			$db->query("INSERT INTO hicrm_users(full_name, user_password, user_email, user_group) VALUES ('".$full_name."','".$password."','".$email."','".$user_group."')");
			$result['status'] = 200;
			$result['message'] = 'Thêm thành công';
			$result['url'] = XC_URL."/admin/users";
		}elseif(isset($method) && $method == "edit"){
			if($user_id <= 0){
				$result['status'] = 400;
				$result['message'] = 'Tài khoản cần cập nhật không hợp lệ';
				echo json_encode($result);
				return;
			}
			$db->query("SELECT user_group FROM hicrm_users WHERE id = '".$user_id."' LIMIT 1");
			$target_user = $db->fetch_object(true);
			if($target_user && intval($target_user->user_group) === 1 && !$this->currentAdminIsSuperAdmin()){
				$result['status'] = 403;
				$result['message'] = 'Không thể chỉnh sửa tài khoản Super Admin.';
				echo json_encode($result);
				return;
			}
			if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)){
				$result['status'] = 400;
				$result['message'] = 'Email không hợp lệ';
				echo json_encode($result);
				return;
			}
			$db->query("SELECT id FROM hicrm_users WHERE user_email = '".$email."' AND id <> '".$user_id."' LIMIT 1");
			if($db->num_row()){
				$result['status'] = 400;
				$result['message'] = 'Email đã tồn tại';
				echo json_encode($result);
				return;
			}
			$fields = array(
				"full_name = '".$full_name."'",
				"user_email = '".$email."'",
				"user_group = '".$user_group."'"
			);
			if(trim((string)($_POST['user_password'] ?? '')) !== ''){
				$fields[] = "user_password = '".$password."'";
			}
			$db->query("UPDATE hicrm_users SET ".implode(',', $fields)." WHERE id = '".$user_id."' LIMIT 1");
			$result['status'] = 200;
			$result['message'] = 'Cập nhật tài khoản thành công';
			$result['url'] = XC_URL."/admin/users";
		}
		echo json_encode($result);
	}
	//end insert user database

	////Update user
	public function updateUser(){
		
		$id = $_POST['userid'];
		$user_fullname = $_POST['user_fullname'];
		$user_address = $_POST['user_address']; 
		$user_phone = $_POST['user_phone']; 
		$user_email = $_POST['user_email']; 
		$user_dept = $_POST['user_dept']; 
		$user_group = $_POST['user_group']; 
		$user_username = $_POST['user_username']; 
		$user_dept = $_POST['user_dept'];
		global $db;
		$result = array();
		if(!empty($user_username)) {
			$db->query("UPDATE hicrm_users SET user_username='".$user_username."' where id = '".$id."'");
		}
		if(!empty($user_fullname)) {
			$db->query("UPDATE hicrm_users SET user_fullname='".$user_fullname."' where id = '".$id."'");
		}
		if(!empty($user_address)) {
			$db->query("UPDATE hicrm_users SET user_address='".$user_address."' where id = '".$id."'");
		}
		if(!empty($user_phone)) {
			$db->query("UPDATE hicrm_users SET user_phone='".$user_phone."' where id = '".$id."'");
		}
		if(!empty($user_email)) {
			$db->query("UPDATE hicrm_users SET user_email='".$user_email."' where id = '".$id."'");
		}
		if(!empty($user_dept)) {
			$db->query("UPDATE hicrm_users SET user_dept='".$user_dept."' where id = '".$id."'");
		}
		if(!empty($user_group)) {
			$db->query("UPDATE hicrm_users SET user_group='".$user_group."' where id = '".$id."'");
		}
		$result['status'] = 200;
		echo json_encode($result);
		
	}
	public function addtypedm(){
		global $db;
	
		$type_name = $_POST['type_name'];
		$type_detail = $_POST['type_detail'];
		$type_status = '1';
		$db->query("INSERT INTO hicrm_type(type_name, type_detail,type_status) VALUES ('".$type_name."','".$type_detail."','".$type_status."') "); 
		$result['status'] = 200;
		
		echo json_encode($result);
		
	}
	public function deleteDmtype(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_type WHERE id = '".$_POST['id']."'");
		if($db->num_row())
		{
			$db->query("UPDATE hicrm_type SET type_status = '99' WHERE id = '".$_POST['id']."'");
			$result["status"] = 200;
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Tài khoản không tồn tại";
		}
		echo json_encode($result);
	}
	public function addImage(){
		global $db;
		$image_name = $_POST['image_name'];
		$image_user_created = $_POST['image_usercreate'];
		$image_created_date = date('Y-m-d H:i:s');
		$image_status = 1;
		$result = array();
		$FinalFilenameFront = "";
		$FinalFilenameFront2 = "";
		$expensions = array("jpeg","jpg","png");
		// echo $_FILES['employee_image']['name'];
		// echo $_FILES['image_file']['size']."ssss";
		if(isset($_FILES['image_file']))
			{
				$errors= array();
				$file_name = $_FILES['image_file']['name'];
				$file_size =$_FILES['image_file']['size'];
				$file_tmp =$_FILES['image_file']['tmp_name'];
				$file_type=$_FILES['image_file']['type'];
				$file_ext=strtolower(end(explode('.',$_FILES['image_file']['name'])));
				$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['image_file']['name']); 
				$FinalFilenameFront = md5(time())."-".$FinalFilename;
				if(in_array($file_ext,$expensions)=== false){
					$errors[]="Extension not allowed, please choose a .png, .jpg file.";
				}
				if($file_size > 5242880){
					$errors[]='File size must be max 2Mb';
				}
				
				if(empty($errors)==true){
					move_uploaded_file($file_tmp,"./uploads/images/".$FinalFilenameFront);
					
				}else
				{
					$result["status"] = 500;
				}
				
			}
		$db->query("INSERT INTO hicrm_images (image_name, image_url, image_user_created, image_created_date, image_status) VALUES('".$image_name."','".$FinalFilenameFront."','".$image_user_created."','".$image_created_date."','".$image_status."')");
		
		$result['status'] = 200;
		$result["url"] = XC_URL."/uploads/images/".$FinalFilenameFront;
		$result["id"] = $FinalFilenameFront;
		echo json_encode($result);

	}

	public function deleteImage(){
		global $db;
		$iid = $_POST['iid'];
		$result = array();
		$db->query("SELECT * FROM hicrm_images WHERE id = '".$iid."'");
		$db->fetch_object(true);
		if($db->num_row()){
			$db->query("UPDATE hicrm_images SET image_status = 99 WHERE id = '".$iid."'");
			
			$result["status"] = 200;
		}else{
			$result['message'] = "Không tìm thấy nhân viên này";
			$result["status"] = 500;
		}
		
		echo json_encode($result);
	}
	public function libraryImageAdd(){
		if(!$this->requireAdminApiPermission('images', false)){ return; }
		global $db;
		$result = array();
		$image_name = isset($_POST['image_name']) ? $db->escapestring(trim($_POST['image_name'])) : '';
		$image_user_created = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
		$image_created_date = date('Y-m-d H:i:s');
		$image_status = 1;
		$FinalFilenameFront = "";
		$expensions = array("jpeg","jpg","png","gif","webp");
		if($image_name === ''){
			$result['status'] = 500;
			$result['message'] = 'Vui lòng nhập tên hình ảnh';
			echo json_encode($result);
			return;
		}
		if(!isset($_FILES['image_file']) || !isset($_FILES['image_file']['name']) || $_FILES['image_file']['name'] === ''){
			$result['status'] = 500;
			$result['message'] = 'Vui lòng chọn hình ảnh';
			echo json_encode($result);
			return;
		}
		$errors = array();
		$file_size = $_FILES['image_file']['size'];
		$file_tmp = $_FILES['image_file']['tmp_name'];
		$file_ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
		$FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['image_file']['name']);
		$FinalFilenameFront = md5(time())."-".$FinalFilename;
		if(in_array($file_ext, $expensions) === false){
			$errors[] = "Extension not allowed, please choose a .png, .jpg, .gif, .webp file.";
		}
		if($file_size > 10485760){
			$errors[] = 'File size must be max 10Mb';
		}
		if(empty($errors) == false){
			$result['status'] = 500;
			$result['message'] = $errors[0];
			echo json_encode($result);
			return;
		}
		if(!move_uploaded_file($file_tmp, "./uploads/images/".$FinalFilenameFront)){
			$result['status'] = 500;
			$result['message'] = 'Không thể tải ảnh lên';
			echo json_encode($result);
			return;
		}
		$db->query("INSERT INTO hicrm_images (image_name, image_url, image_user_created, image_created_date, image_status) VALUES('".$image_name."','".$FinalFilenameFront."','".$image_user_created."','".$image_created_date."','".$image_status."')");
		$result['status'] = 200;
		$result['message'] = 'Thêm hình ảnh thành công';
		$result["url"] = XC_URL."/uploads/images/".$FinalFilenameFront;
		echo json_encode($result);
	}
	public function libraryImageUpdate(){
		if(!$this->requireAdminApiPermission('images', false)){ return; }
		global $db;
		$result = array();
		$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
		$image_name = isset($_POST['image_name']) ? $db->escapestring(trim($_POST['image_name'])) : '';
		if($id <= 0){
			$result['status'] = 500;
			$result['message'] = 'Không tìm thấy hình ảnh';
			echo json_encode($result);
			return;
		}
		if($image_name === ''){
			$result['status'] = 500;
			$result['message'] = 'Vui lòng nhập tên hình ảnh';
			echo json_encode($result);
			return;
		}
		$db->query("SELECT * FROM hicrm_images WHERE id = '".$id."' AND image_status NOT IN(99) LIMIT 1");
		$image = $db->fetch_object(true);
		if(!$image){
			$result['status'] = 500;
			$result['message'] = 'Hình ảnh không tồn tại';
			echo json_encode($result);
			return;
		}
		$updateimage = "";
		if(isset($_FILES['image_file']) && isset($_FILES['image_file']['name']) && $_FILES['image_file']['name'] !== ''){
			$expensions = array("jpeg","jpg","png","gif","webp");
			$errors = array();
			$file_size = $_FILES['image_file']['size'];
			$file_tmp = $_FILES['image_file']['tmp_name'];
			$file_ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
			$FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['image_file']['name']);
			$FinalFilenameFront = md5(time())."-".$FinalFilename;
			if(in_array($file_ext, $expensions) === false){
				$errors[] = "Extension not allowed, please choose a .png, .jpg, .gif, .webp file.";
			}
			if($file_size > 10485760){
				$errors[] = 'File size must be max 10Mb';
			}
			if(empty($errors) == false){
				$result['status'] = 500;
				$result['message'] = $errors[0];
				echo json_encode($result);
				return;
			}
			if(!move_uploaded_file($file_tmp, "./uploads/images/".$FinalFilenameFront)){
				$result['status'] = 500;
				$result['message'] = 'Không thể tải ảnh lên';
				echo json_encode($result);
				return;
			}
			$updateimage = ", image_url = '".$FinalFilenameFront."'";
		}
		$db->query("UPDATE hicrm_images SET image_name = '".$image_name."'".$updateimage." WHERE id = '".$id."' LIMIT 1");
		$result['status'] = 200;
		$result['message'] = 'Cập nhật hình ảnh thành công';
		echo json_encode($result);
	}
	public function libraryImageDelete(){
		if(!$this->requireAdminApiPermission('images', false)){ return; }
		global $db;
		$result = array();
		$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
		$db->query("SELECT * FROM hicrm_images WHERE id = '".$id."' LIMIT 1");
		$db->fetch_object(true);
		if($db->num_row()){
			$db->query("UPDATE hicrm_images SET image_status = 99 WHERE id = '".$id."'");
			$result["status"] = 200;
			$result['message'] = 'Đã xóa hình ảnh';
		}else{
			$result['message'] = "Không tìm thấy hình ảnh";
			$result["status"] = 500;
		}
		echo json_encode($result);
	}
    public function load_work_schedule(){
		global $db;
		$id = $_POST['id'];
		$db->query("SELECT * FROM hicrm_calendar_works WHERE id = '".$id."'");
		$work = $db->fetch_object(true);

		$result['src'] = XC_URL ."/uploads/files/".$work->calendar_work_file;
		$result['status'] = 200;
		echo json_encode($result);
	}
	public function updateIntroduce(){
		global $db;
		$result = array();
		$id = $_POST['type_id'];

		$page_content = $db->escapestring($_POST['content']);
		// var_dump($page_content);
		$page_uid = $_POST['userid'];
		$page_status = 1;
		$page_created_date = date('Y-m-d H:i:s');
		// echo "UPDATE hicrm_introduce SET introduce_id_type ='".$id."',introduce_content='".$page_content."',introduce_uid = '".$page_uid."',introduce_created_date='".$page_created_date."' WHERE introduce_id_type ='".$id."'";
		$db->query("UPDATE hicrm_introduce SET introduce_content='".$page_content."',introduce_uid = '".$page_uid."',introduce_created_date='".$page_created_date."' WHERE id = '".$id."'");
		
		$result['message'] = "Thành công";
		$result['status'] = 200;
		
		echo json_encode($result);
	}
	public function loadIntroduce(){
		global $db;
		$id = $_POST['id'];
		$db->query("SELECT *, i.id as id FROM hicrm_introduce as i
					LEFT JOIN hicrm_type as t ON i.introduce_id_type = t.id WHERE i.introduce_id_type= '".$id."'");
		$introduce = $db->fetch_object(true);
		$result['title'] = $introduce->type_name;
		$result['content'] = $introduce->introduce_content;
		$result['status'] = 200;
		echo json_encode($result);

	}
	
	public function calendarEmployee(){
		global $db;
		$result = array();
		$id = $_POST['eid'];
		// echo $id;
		$employee_calendar = $_POST['employee_calendar'];
		$employee_shift = $_POST['employee_shift'];
		$db->query("SELECT * FROM hicrm_employees WHERE id = '".$id."'");
		$db->fetch_object(true);
		if($db->num_row()){
				$db->query("UPDATE hicrm_employees SET employee_calendar='".$employee_calendar."',employee_shift='".$employee_shift."' WHERE id = '".$id."' ");
				$result['message'] = "Sửa thành công";
				$result['status'] = 200;
			
		}else{
			$result['message'] = "Thất bại";
			$result['status'] = 500;
		}
		echo json_encode($result);

	}
	public function addBooking(){
		global $db;
		$result = array();
		
		$db->query("INSERT INTO 
		hicrm_bookings(booking_person_name, booking_person_gender, booking_person_year, booking_person_address, booking_person_phone, booking_doctor, booking_date, booking_hour, booking_description, booking_created_date)
		VALUES ('".$_POST['booking_person_name']."','".$_POST['booking_person_gender']."','".$_POST['booking_person_year']."','".$_POST['booking_person_address']."','".$_POST['booking_person_phone']."',
		'".$_POST['booking_doctor']."','".$_POST['booking_date']."','".$_POST['booking_hour']."','".$_POST['booking_description']."', '".date("Y-m-d H:i:s")."')");
		
		$result['status'] = 200;
		$result['message'] = "Bộ phận tiếp nhận sẽ sớm liên hệ để xác nhận lịch hẹn.";
		echo json_encode($result);
	}
	public function addFeedback(){
	    global $db;
		$result = array(
			'status' => 400,
			'message' => 'Gửi yêu cầu liên hệ thất bại.',
			'return_url' => XC_URL.'/lien-he.html'
		);
		$date = date("Y-m-d H:i:s");
		$customer_name = trim(isset($_POST['customer_name']) ? $_POST['customer_name'] : '');
		$customer_phone = trim(isset($_POST['customer_phone']) ? $_POST['customer_phone'] : '');
		$customer_email = trim(isset($_POST['customer_email']) ? $_POST['customer_email'] : '');
		$customer_address = trim(isset($_POST['customer_address']) ? $_POST['customer_address'] : '');
		$content = trim(isset($_POST['content']) ? $_POST['content'] : '');
		$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;

		if($customer_name === '' || $customer_phone === '' || $customer_email === '' || $customer_address === '' || $content === ''){
			$result['message'] = 'Vui lòng nhập đầy đủ họ tên, số điện thoại, email, địa chỉ và nội dung liên hệ.';
			echo json_encode($result);
			return;
		}

		if(!filter_var($customer_email, FILTER_VALIDATE_EMAIL)){
			$result['message'] = 'Email không đúng định dạng.';
			echo json_encode($result);
			return;
		}

		$db->query("INSERT INTO hicrm_customer_feedback(customer_name, customer_phone, customer_email, customer_address, content, status, rating, create_date) VALUES ('".$db->escapestring($customer_name)."','".$db->escapestring($customer_phone)."','".$db->escapestring($customer_email)."','".$db->escapestring($customer_address)."','".$db->escapestring($content)."','0','".$rating."','".$date."')");
		
		$result['status'] = 200;
		$result['message'] = "Gửi yêu cầu liên hệ thành công!";
		echo json_encode($result);
	}
	public function approveBooking()
	{
		global $db;
		// echo $_POST['bid'];
		$bid = $_POST['bid'];
		$result = array();
		$db->query("SELECT * FROM hicrm_bookings WHERE id = '".$_POST['bid']."'");
		$db->fetch_object(true);
		if($db->num_row()){
				$db->query("UPDATE hicrm_bookings SET booking_status='2' WHERE id = '".$bid."'");
				$result['message'] = "Duyệt thành công";
				$result['status'] = 200;
			
		}else{
			$result['message'] = "Không có dữ liệu";
			$result['status'] = 500;
		}

		echo json_encode($result);
	}
	//===== API NEWS === 
	public function news()
	{
		if(!$this->requireAdminApiPermission('events', false)){ return; }
		global $db;
		$new_name = $_POST['new_name'];
		$new_description = $_POST['new_description'];
		$new_content = $db->escapestring($_POST['new_content']);
		$new_user_created = $_POST['new_user_created'];
		$new_created_date = date('Y-m-d H:i:s');
		$FinalFilenameFront = "";
		$FinalFilenameFront2 = "";
		$expensions = array("jpeg","jpg","png");
		$method = $_POST['method'];
		$result = array();
		$id = $_POST['nid'];
		
		if(isset($method) && $method == "add")
		{
			$FinalFilenameFront = "";
			$FinalFilenameFront2 = "";
			//echo $_FILES['hinhanh']['name']."ssss";
			if ($_FILES['new_image']['error'] == 4) {
				// Không có file upload → gán hình mặc định
				$FinalFilenameFront = '';
			}else{
				$errors= array();
				$file_name = $_FILES['new_image']['name'];
				$file_size =$_FILES['new_image']['size'];
				$file_tmp =$_FILES['new_image']['tmp_name'];
				$file_type=$_FILES['new_image']['type'];
				$file_ext=strtolower(end(explode('.',$_FILES['new_image']['name'])));
				$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['new_image']['name']); 
				$FinalFilenameFront = md5(time())."-".$FinalFilename;
				if(in_array($file_ext,$expensions)=== false){
					$errors[]="Extension not allowed, please choose a .png, .jpg file.";
				}
				if($file_size > 5242880){
					$errors[]='File size must be max 2Mb';
				}
				if(empty($errors)==true){
					move_uploaded_file($file_tmp,"./uploads/news/".$FinalFilenameFront);
					
				}else
				{
					$result["status"] = 500;
				}
				
			}
			$db->query("INSERT INTO hicrm_news(new_name,new_description,new_content,new_image,new_user_created,new_status,new_created_date)
			VALUES('".$_POST['new_name']."','".$_POST['new_description']."','".$new_content."','".$FinalFilenameFront."','".$new_user_created."',1,'".$new_created_date."')");
			$result["status"] = 200;
			$result["url"] = XC_URL."/uploads/news/".$FinalFilenameFront;
			$result["id"] = $FinalFilenameFront;
			$result['message'] = 'Thêm thành công';
			$result['returnUrl'] = XC_URL."/admin/news";
		}
		elseif(isset($method) && $method == "edit")
		{
			// echo "ssss";
			// echo $id .'id';
			$db->query("SELECT * FROM hicrm_news WHERE id = '".$id."'");
			$db->fetch_object(true);
			if($db->num_row())
			{
				// echo "aa";
				$FinalFilenameFront = "";
				//echo $_FILES['hinhanh']['name']."ssss";
				if(isset($_FILES['new_image']))
				{
					$errors= array();
					$file_name = $_FILES['new_image']['name'];
					$file_size =$_FILES['new_image']['size'];
					$file_tmp =$_FILES['new_image']['tmp_name'];
					$file_type=$_FILES['new_image']['type'];
					$file_ext=strtolower(end(explode('.',$_FILES['new_image']['name'])));
					$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['new_image']['name']); 
					$FinalFilenameFront = md5(time())."-".$FinalFilename;
					if(in_array($file_ext,$expensions)=== false){
						$errors[]="Extension not allowed, please choose a .png, .jpg file.";
					}
					if($file_size > 5242880){
						$errors[]='File size must be max 2Mb';
					}
					if(empty($errors)==true){
						move_uploaded_file($file_tmp,"./uploads/news/".$FinalFilenameFront);
						
					}else
					{
						$result["status"] = 500;
					}
					
				}
				
				$updateimage = ($FinalFilenameFront != "")? ", new_image = '".$FinalFilenameFront."'" : "";
				// echo "UPDATE hicrm_news SET new_name = '".$_POST['new_name']."',new_description = '".$_POST['new_description']."', new_content = '".$new_content."'".$updateimage." WHERE id = '".$id."'";
				$db->query("UPDATE hicrm_news SET new_name = '".$_POST['new_name']."',new_description = '".$_POST['new_description']."', new_content = '".$new_content."'".$updateimage." WHERE id = '".$id."'");
				$result["status"] = 200;
				$result['message'] = 'Sửa thành công';
				$result['returnUrl'] = XC_URL."/admin/news";
			}
			else
			{
				$result["status"] = 500;
				$result["message"] = "Không tồn tại nội dung này!";
			}
		}
		echo json_encode($result);
	}
	//====END====/

	//===== API events === 
	public function events()
	{
		if(!$this->requireAdminApiPermission('events', false)){ return; }
		global $db;
		$event_name = isset($_POST['event_name']) ? $db->escapestring($_POST['event_name']) : '';
		$event_description = isset($_POST['event_description']) ? $db->escapestring($_POST['event_description']) : '';
		$event_content = isset($_POST['event_content']) ? $db->escapestring($_POST['event_content']) : '';
		$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
		$event_type = isset($_POST['event_type']) ? intval($_POST['event_type']) : 0;
		$event_hot = isset($_POST['event_hot']) ? intval($_POST['event_hot']) : 0;
		$event_user_created = isset($_POST['event_user_created']) ? intval($_POST['event_user_created']) : 0;
		$event_status = isset($_POST['event_status']) ? intval($_POST['event_status']) : 0;
		$event_created_date = date('Y-m-d H:i:s');
		$FinalFilenameFront = "";
		$FinalFilenameFront2 = "";
		$expensions = array("jpeg","jpg","png");
		$method = isset($_POST['method']) ? $_POST['method'] : '';
		$result = array();
		$id = isset($_POST['eid']) ? intval($_POST['eid']) : 0;

		if($event_name === '' || $event_content === ''){
			$result["status"] = 422;
			$result["message"] = "Vui lòng nhập tiêu đề và nội dung.";
			echo json_encode($result);
			return;
		}

		$upload_event_image = function() use (&$result, $expensions) {
			if(!isset($_FILES['event_image']) || !is_array($_FILES['event_image'])){
				return '';
			}

			$file_error = isset($_FILES['event_image']['error']) ? (int)$_FILES['event_image']['error'] : 4;
			if($file_error === 4){
				return '';
			}

			if($file_error !== 0){
				$result["status"] = 500;
				$result["message"] = "Tải ảnh đại diện không thành công.";
				return false;
			}

			$file_name = isset($_FILES['event_image']['name']) ? trim((string)$_FILES['event_image']['name']) : '';
			$file_size = isset($_FILES['event_image']['size']) ? (int)$_FILES['event_image']['size'] : 0;
			$file_tmp = isset($_FILES['event_image']['tmp_name']) ? $_FILES['event_image']['tmp_name'] : '';
			$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

			if($file_name === '' || $file_tmp === ''){
				return '';
			}
			if(in_array($file_ext, $expensions) === false){
				$result["status"] = 422;
				$result["message"] = "Ảnh đại diện chỉ hỗ trợ định dạng JPG hoặc PNG.";
				return false;
			}
			if($file_size > 5242880){
				$result["status"] = 422;
				$result["message"] = "Ảnh đại diện phải nhỏ hơn 5MB.";
				return false;
			}

			$final_filename = preg_replace('`[^a-z0-9-_.]`i','',$file_name);
			$final_filename = md5(time().$file_name)."-".$final_filename;
			if(!move_uploaded_file($file_tmp, "./uploads/events/".$final_filename)){
				$result["status"] = 500;
				$result["message"] = "Không thể lưu ảnh đại diện lên máy chủ.";
				return false;
			}

			return $final_filename;
		};
		
		if(isset($method) && $method == "add")
		{
			$FinalFilenameFront = $upload_event_image();
			if($FinalFilenameFront === false){
				echo json_encode($result);
				return;
			}
			$db->query("INSERT INTO hicrm_events(user_id,event_name,event_description,event_content,event_image,event_type,event_hot,event_user_created,event_status,event_created_date)
			VALUES('".$user_id."','".$event_name."','".$event_description."','".$event_content."','".$FinalFilenameFront."','".$event_type."','".$event_hot."','".$event_user_created."','".$event_status."','".$event_created_date."')");
			$result["status"] = 200;
			$result["url"] = XC_URL."/uploads/events/".$FinalFilenameFront;
			$result["id"] = $FinalFilenameFront;
			$result['message'] = 'Thêm thành công';
			$result['returnUrl'] = XC_URL."/admin/events";
		}
		elseif(isset($method) && $method == "edit")
		{
			// echo "ssss";
			// echo $id .'id';
			$db->query("SELECT * FROM hicrm_events WHERE id = '".$id."'");
			$db->fetch_object(true);
			if($db->num_row())
			{
				$FinalFilenameFront = $upload_event_image();
				if($FinalFilenameFront === false){
					echo json_encode($result);
					return;
				}
				$updateimage = ($FinalFilenameFront != "")? ", event_image = '".$FinalFilenameFront."'" : "";
				$db->query("UPDATE hicrm_events SET user_id = '".$user_id."', event_name = '".$event_name."', event_description = '".$event_description."', event_content = '".$event_content."', event_type = '".$event_type."', event_hot = '".$event_hot."', event_user_created = '".$event_user_created."', event_status = '".$event_status."', event_created_date = '".$event_created_date."'".$updateimage." WHERE id = '".$id."'");
				$result["status"] = 200;
				$result['message'] = 'Sửa thành công';
				$result['returnUrl'] = XC_URL."/admin/events";
			}
			else
			{
				$result["status"] = 500;
				$result["message"] = "Không tồn tại nội dung này!";
			}
		}
		echo json_encode($result);
	}
	//====END====/

	//=======API product ========== ////
	public function productActions()
	{
		global $db;
		$product_code = $_POST['product_code'];
		$product_name = $_POST['product_name'];
		$product_price = $_POST['product_price'];
		$product_discount = $_POST['product_discount'];
		$product_category = $_POST['product_category'];
		$product_description = $db->escapestring($_POST['product_description']);
		$product_unit = $_POST['product_unit'];
		$product_created_time = date('Y-m-d H:i:s');
		$product_vat_name = '';
		$product_barcode = '';
		$FinalFilenameFront = "";
		$FinalFilenameFront2 = "";
		$expensions = array("jpeg","jpg","png");
		$method = $_POST['method'];
		$result = array();
		$id = $_POST['pid'];
		if(isset($method) && $method == "new")
		{
			$FinalFilenameFront = "";
			$FinalFilenameFront2 = "";
			//echo $_FILES['hinhanh']['name']."ssss";
			if ($_FILES['product_image']['error'] == 4) {
				// Không có file upload → gán hình mặc định
				$FinalFilenameFront = '';
			}else{
				$errors= array();
				$file_name = $_FILES['product_image']['name'];
				$file_size =$_FILES['product_image']['size'];
				$file_tmp =$_FILES['product_image']['tmp_name'];
				$file_type=$_FILES['product_image']['type'];
				$file_ext=strtolower(end(explode('.',$_FILES['product_image']['name'])));
				$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['product_image']['name']); 
				$FinalFilenameFront = md5(time())."-".$FinalFilename;
				if(in_array($file_ext,$expensions)=== false){
					$errors[]="Extension not allowed, please choose a .png, .jpg file.";
				}
				if($file_size > 5242880){
					$errors[]='File size must be max 2Mb';
				}
				if(empty($errors)==true){
					move_uploaded_file($file_tmp,"./uploads/products/".$FinalFilenameFront);
					
				}else
				{
					$result["status"] = 500;
				}
				
			}
			$db->query("INSERT INTO hicrm_products(product_name,product_code,product_barcode,product_vat_name,product_unit,product_category,product_price,product_discount,product_tax_id,product_description,product_image,product_created_time,product_status) VALUES ('".$product_name."','".$product_code."','".$product_barcode."','".$product_vat_name."','".$product_unit."','".$product_category."','".$product_price."','".$product_discount."',0,'".$product_description."','".$FinalFilenameFront."','".$product_created_time."',1)");			
			$result["status"] = 200;
			$result["url"] = XC_URL."/uploads/products/".$FinalFilenameFront;
			$result["id"] = $FinalFilenameFront;
			$result['message'] = 'Thêm thành công';
			$result['returnUrl'] = XC_URL."/admin/products";
		}
		elseif(isset($method) && $method == "update")
		{
			// echo "ssss";
			// echo $id .'id';
			$db->query("SELECT * FROM hicrm_products WHERE id = '".$id."'");
			$db->fetch_object(true);
			if($db->num_row())
			{
				// echo "aa";
				$FinalFilenameFront = "";
				//echo $_FILES['hinhanh']['name']."ssss";
				if(isset($_FILES['product_image']))
				{
					$errors= array();
					$file_name = $_FILES['product_image']['name'];
					$file_size =$_FILES['product_image']['size'];
					$file_tmp =$_FILES['product_image']['tmp_name'];
					$file_type=$_FILES['product_image']['type'];
					$file_ext=strtolower(end(explode('.',$_FILES['product_image']['name'])));
					$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['product_image']['name']); 
					$FinalFilenameFront = md5(time())."-".$FinalFilename;
					if(in_array($file_ext,$expensions)=== false){
						$errors[]="Extension not allowed, please choose a .png, .jpg file.";
					}
					if($file_size > 5242880){
						$errors[]='File size must be max 2Mb';
					}
					if(empty($errors)==true){
						move_uploaded_file($file_tmp,"./uploads/products/".$FinalFilenameFront);
						
					}else
					{
						$result["status"] = 500;
					}
					
				}
				
				$updateimage = ($FinalFilenameFront != "")? ", product_image = '".$FinalFilenameFront."'" : "";
				// echo "UPDATE hicrm_products SET product_name='".$product_name."',product_code='".$product_code."',product_barcode='".$product_barcode."',product_vat_name='".$product_vat_name."',product_unit='".$product_unit."',product_category='".$product_category."',product_price='".$product_price."',product_discount='".$product_discount."',product_tax_id=0,product_description='".$product_description."".$updateimage." WHERE id='".$id."'";
				$db->query("UPDATE hicrm_products SET product_name='".$product_name."',product_code='".$product_code."',product_barcode='".$product_barcode."',product_vat_name='".$product_vat_name."',product_unit='".$product_unit."',product_category='".$product_category."',product_price='".$product_price."',product_discount='".$product_discount."',product_tax_id=0,product_description='".$product_description."'".$updateimage." WHERE id='".$id."'");

				$result["status"] = 200;
				$result['message'] = 'Sửa thành công';
				$result['returnUrl'] = XC_URL."/admin/products";
			}
			else
			{
				$result["status"] = 500;
				$result["message"] = "Không tồn tại nội dung này!";
			}
		}
		echo json_encode($result);
	}


	///===END====//

	public function addnews()
	{
		global $db;
		$result = array();
		$updatetype = $_POST['updatetype'];
		if(isset($_POST['updatetype']) && $_POST['updatetype'] == "new")
		{
			$FinalFilenameFront = "";
			$FinalFilenameFront2 = "";
			//echo $_FILES['hinhanh']['name']."ssss";
			if ($_FILES['hinhanh']['error'] == 4) {
				// Không có file upload → gán hình mặc định
				$FinalFilenameFront = $default_image;
			}else{
				$errors= array();
				$file_name = $_FILES['hinhanh']['name'];
				$file_size =$_FILES['hinhanh']['size'];
				$file_tmp =$_FILES['hinhanh']['tmp_name'];
				$file_type=$_FILES['hinhanh']['type'];
				$file_ext=strtolower(end(explode('.',$_FILES['hinhanh']['name'])));
				$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['hinhanh']['name']); 
				$FinalFilenameFront = md5(time())."-".$FinalFilename;
				if(in_array($file_ext,$expensions)=== false){
					$errors[]="Extension not allowed, please choose a .png, .jpg file.";
				}
				if($file_size > 5242880){
					$errors[]='File size must be max 2Mb';
				}
				if(empty($errors)==true){
					move_uploaded_file($file_tmp,"./uploads/general/".$FinalFilenameFront);
					
				}else
				{
					$result["status"] = 500;
				}
				
			}
			
			$db->query("INSERT INTO bds_news(news_title,news_category,news_content,news_author,news_feature,news_view,news_image) VALUES('".$_POST['title']."','".$_POST['category']."','".$_POST['noidung']."','".$_SESSION['staff']['id']."',1,0,'".$FinalFilenameFront."')");
			$result["status"] = 200;
			$result["url"] = XC_URL."/uploads/general/".$FinalFilenameFront;
			$result["id"] = $FinalFilenameFront;
		}
		elseif(isset($_POST['updatetype']) && $_POST['updatetype'] == "edit")
		{
			$db->query("SELECT * FROM bds_news WHERE id = '".$_POST['id']."'");
			if($db->num_row())
			{
				$FinalFilenameFront = "";
				//echo $_FILES['hinhanh']['name']."ssss";
				if(isset($_FILES['hinhanh']))
				{
					$errors= array();
					$file_name = $_FILES['hinhanh']['name'];
					$file_size =$_FILES['hinhanh']['size'];
					$file_tmp =$_FILES['hinhanh']['tmp_name'];
					$file_type=$_FILES['hinhanh']['type'];
					$file_ext=strtolower(end(explode('.',$_FILES['hinhanh']['name'])));
					$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['hinhanh']['name']); 
					$FinalFilenameFront = md5(time())."-".$FinalFilename;
					if(in_array($file_ext,$expensions)=== false){
						$errors[]="Extension not allowed, please choose a .png, .jpg file.";
					}
					if($file_size > 5242880){
						$errors[]='File size must be max 2Mb';
					}
					if(empty($errors)==true){
						move_uploaded_file($file_tmp,"./uploads/general/".$FinalFilenameFront);
						
					}else
					{
						$result["status"] = 500;
					}
					
				}
				
				$updateimage = ($FinalFilenameFront != "")? ", news_image = '".$FinalFilenameFront."'" : "";
				$db->query("UPDATE bds_news SET news_title = '".$_POST['title']."', news_category = '".$_POST['category']."', news_content = '".$_POST['noidung']."' ".$updateimage." WHERE id = '".$_POST['id']."'");
				$result["status"] = 200;
			}
			else
			{
				$result["status"] = 500;
				$result["message"] = "Không tồn tại nội dung này!";
			}
		}
		echo json_encode($result);
	}
	//==========API Category ==============//
	public function addCategory(){
		global $db;
		$result = array();
		$db->query("INSERT INTO hicrm_categories(category_name, category_description, category_parent, category_orderby, category_status) 
		VALUES ('".$_POST['category_name']."','".$_POST['category_des']."','".$_POST['category_parent']."','".$_POST['category_orderby']."','1')");
		$result['status'] = 200;
        $result['message'] = "Thêm hành công!";
        echo json_encode($result);
	}
	//====END====//
	//==========API SERVICES ===========//
	public function services(){
		global $db;
		$service_name = $_POST['service_name'];
		$service_description = $db->escapestring($_POST['service_description']);
		$service_created_date = date('Y-m-d H:i:s');
		$FinalFilenameFront = "";
		$FinalFilenameFront2 = "";
		$expensions = array("jpeg","jpg","png");
		$method = $_POST['method'];
		$result = array();
		$id = $_POST['sid'];
		$service_category = $_POST['service_category'];
		
		if(isset($method) && $method == "add")
		{
			$FinalFilenameFront = "";
			$FinalFilenameFront2 = "";
			//echo $_FILES['hinhanh']['name']."ssss";
			if ($_FILES['service_image']['error'] == 4) {
				// Không có file upload → gán hình mặc định
				$FinalFilenameFront = '';
			}else{
				$errors= array();
				$file_name = $_FILES['service_image']['name'];
				$file_size =$_FILES['service_image']['size'];
				$file_tmp =$_FILES['service_image']['tmp_name'];
				$file_type=$_FILES['service_image']['type'];
				$file_ext=strtolower(end(explode('.',$_FILES['service_image']['name'])));
				$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['service_image']['name']); 
				$FinalFilenameFront = md5(time())."-".$FinalFilename;
				if(in_array($file_ext,$expensions)=== false){
					$errors[]="Extension not allowed, please choose a .png, .jpg file.";
				}
				if($file_size > 5242880){
					$errors[]='File size must be max 2Mb';
				}
				if(empty($errors)==true){
					move_uploaded_file($file_tmp,"./uploads/services/".$FinalFilenameFront);
					
				}else
				{
					$result["status"] = 500;
				}
				
			}
			$db->query("INSERT INTO hicrm_service(service_name, service_description, service_image, service_category, service_created_date, service_status) VALUES ('".$service_name."','".$service_description."','".$FinalFilenameFront."','".$service_category."','".$service_created_date."','1')");
			
			$result["status"] = 200;
			$result["url"] = XC_URL."/uploads/services/".$FinalFilenameFront;
			$result["id"] = $FinalFilenameFront;
			$result['message'] = 'Thêm thành công';
			$result['returnUrl'] = XC_URL."/admin/service/".$service_category;
		}
		elseif(isset($method) && $method == "edit")
		{
			$db->query("SELECT * FROM hicrm_service WHERE id = '".$id."'");
			$db->fetch_object(true);
			if($db->num_row())
			{
				// echo "aa";
				$FinalFilenameFront = "";
				//echo $_FILES['hinhanh']['name']."ssss";
				if(isset($_FILES['service_image']))
				{
					$errors= array();
					$file_name = $_FILES['service_image']['name'];
					$file_size =$_FILES['service_image']['size'];
					$file_tmp =$_FILES['service_image']['tmp_name'];
					$file_type=$_FILES['service_image']['type'];
					$file_ext=strtolower(end(explode('.',$_FILES['service_image']['name'])));
					$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['service_image']['name']); 
					$FinalFilenameFront = md5(time())."-".$FinalFilename;
					if(in_array($file_ext,$expensions)=== false){
						$errors[]="Extension not allowed, please choose a .png, .jpg file.";
					}
					if($file_size > 5242880){
						$errors[]='File size must be max 2Mb';
					}
					if(empty($errors)==true){
						move_uploaded_file($file_tmp,"./uploads/services/".$FinalFilenameFront);
						
					}else
					{
						$result["status"] = 500;
					}
					
				}
				
				$updateimage = ($FinalFilenameFront != "")? ", service_image = '".$FinalFilenameFront."'" : "";
				// echo "UPDATE hicrm_service SET service_name='".$service_name."',service_description='".$service_description."', ".$updateimage." service_category='".$service_category."' WHERE id = '".$id."'";
				$db->query("UPDATE hicrm_service SET service_name='".$service_name."',service_description='".$service_description."' ".$updateimage." ,service_category='".$service_category."' WHERE id = '".$id."'");
				$result["status"] = 200;
				$result['message'] = 'Sửa thành công';
				$result['returnUrl'] = XC_URL."/admin/service/".$service_category;
			}
			else
			{
				$result["status"] = 500;
				$result["message"] = "Không tồn tại nội dung này!";
			}
		}
		echo json_encode($result);
	}
	public function deleteservices(){
		$id = $_POST['id'];
		$table = 'hicrm_service';
		$row = 'service_status';
		$this->model->action($id, $row, $table);
		$result['status'] = 200;
		echo json_encode($result);
	}
	//===========END==============//
	//==============API LỊCH CÔNG TÁC ===========//
	public function calendar_works(){
		global $db;
		$calendar_work_name = $_POST['calendar_work_name'];
		$calendar_work_to = $_POST['calendar_work_to'];
		$calendar_work_from = $_POST['calendar_work_from'];
		$calendar_work_content = $db->escapestring($_POST['calendar_work_description']);
		$calendar_work_created_date = date('Y-m-d H:i:s');
		$FinalFilenameFront = "";
		$FinalFilenameFront2 = "";
		$expensions = array("pdf","doc");
		$method = $_POST['method'];
		$result = array();
		$id = $_POST['wid'];
		$uid = $_POST['uid'];
		
		if(isset($method) && $method == "add")
		{
			$FinalFilenameFront = "";
			$FinalFilenameFront2 = "";
			
			//echo $_FILES['hinhanh']['name']."ssss";
			if ($_FILES['calendar_work_file']['error'] == 4) {
				// Không có file upload → gán hình mặc định
				$FinalFilenameFront = '';
			}else{
				$errors= array();
				$file_name = $_FILES['calendar_work_file']['name'];
				$file_size =$_FILES['calendar_work_file']['size'];
				$file_tmp =$_FILES['calendar_work_file']['tmp_name'];
				$file_type=$_FILES['calendar_work_file']['type'];
				$file_ext=strtolower(end(explode('.',$_FILES['calendar_work_file']['name'])));
				$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['calendar_work_file']['name']); 
				$FinalFilenameFront = md5(time())."-".$FinalFilename;
				if(in_array($file_ext,$expensions)=== false){
					$errors[]="Extension not allowed, please choose a .pdf, .doc file.";
				}
				if($file_size > 5242880){
					$errors[]='File size must be max 2Mb';
				}
				if(empty($errors)==true){
					move_uploaded_file($file_tmp,"./uploads/files/".$FinalFilenameFront);
					
				}else
				{
					$result["status"] = 500;
				}
				
			}
			$db->query("INSERT INTO hicrm_calendar_works(calendar_work_name, calendar_work_content, calendar_work_file, calendar_work_from_date, calendar_work_to_date, calendar_work_created_date, calendar_work_user_created, calendar_status) VALUES ('".$_POST['calendar_work_name']."','".$_POST['calendar_work_content']."','".$FinalFilenameFront."','".$_POST['calendar_work_from']."','".$_POST['calendar_work_to']."','".$calendar_work_created_date."','".$_POST['uid']."','1')");

			
			$result["status"] = 200;
			$result["url"] = XC_URL."/uploads/files/".$FinalFilenameFront;
			$result["id"] = $FinalFilenameFront;
			$result['message'] = 'Thêm thành công';
			$result['returnUrl'] = XC_URL."/admin/lichcongtac/";
		}
		elseif(isset($method) && $method == "edit")
		{
			$db->query("SELECT * FROM hicrm_calendar_works WHERE id = '".$id."'");
			$db->fetch_object(true);
			if($db->num_row())
			{
				// echo "aa";
				$FinalFilenameFront = "";
				//echo $_FILES['hinhanh']['name']."ssss";
				if(isset($_FILES['calendar_work_file']))
				{
					$errors= array();
					$file_name = $_FILES['calendar_work_file']['name'];
					$file_size =$_FILES['calendar_work_file']['size'];
					$file_tmp =$_FILES['calendar_work_file']['tmp_name'];
					$file_type=$_FILES['calendar_work_file']['type'];
					$file_ext=strtolower(end(explode('.',$_FILES['calendar_work_file']['name'])));
					$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['calendar_work_file']['name']); 
					$FinalFilenameFront = md5(time())."-".$FinalFilename;
					if(in_array($file_ext,$expensions)=== false){
						$errors[]="Extension not allowed, please choose a .doc, .pdf file.";
					}
					if($file_size > 5242880){
						$errors[]='File size must be max 2Mb';
					}
					if(empty($errors)==true){
						move_uploaded_file($file_tmp,"./uploads/files/".$FinalFilenameFront);
						
					}else
					{
						$result["status"] = 500;
					}
					
				}
				
				$updatefile = ($FinalFilenameFront != "")? ", calendar_work_file = '".$FinalFilenameFront."'" : "";
				// echo "UPDATE hicrm_service SET service_name='".$service_name."',service_description='".$service_description."' ".$updateimage." ,service_category='".$service_category."' WHERE id = '".$id."'";
				$db->query("UPDATE hicrm_calendar_works SET calendar_work_name='".$calendar_work_name."',calendar_work_content='".$calendar_work_content."' ".$updatefile.",calendar_work_from_date='".$calendar_work_from."',calendar_work_to_date='".$calendar_work_to."' WHERE id = '".$id."' ");

				
				$result["status"] = 200;
				$result['message'] = 'Sửa thành công';
				$result['returnUrl'] = XC_URL."/admin/lichcongtac/";
			}
			else
			{
				$result["status"] = 500;
				$result["message"] = "Không tồn tại nội dung này!";
			}
		}
		echo json_encode($result);
	}
	//=========END=========//
	//======================== ORDER API =================================//
    
	public function addorders(){
        // echo "<pre>";
        // print_r($_POST);
        // echo "</pre>";
        global $db;
        $result = array();
        $db->query("INSERT INTO hicrm_orders(order_customer_id, order_employee_id, order_payment_policy_id, order_warehouse_id, order_code, order_name_contact, order_payment_active, order_description, order_date, order_active, order_delivery_date, order_create_date, order_status) VALUES ('".$_POST['order_customer_id']."','".$_POST['order_employee_id']."','".$_POST['order_payment_policy_id']."','".$_POST['order_warehouse_id']."','".$_POST['order_code']."','".$_POST['order_name_contact']."','".$_POST['order_payment_active']."','".$_POST['order_description']."','".$_POST['order_date']."','".$_POST['order_active']."','".$_POST['order_delivery_date']."','".date("Y-m-d H:i:s")."','1')");
        //check order code
        $db->query("SELECT * FROM hicrm_orders WHERE order_code = '".$_POST['order_code']."' LIMIT 1 ");
        $id_oder = $db->fetch_object(true)->id;
        
        foreach($_POST['products'] as $product)
        {
			
            $db->query("SELECT * FROM hicrm_category_products WHERE cat_product_code = '".$product[0]."' AND cat_product_status NOT IN(99) ");
            $row_product = $db->fetch_object(true);
			
            if($db->num_row($row_product)){
                $id_product = $row_product->id;
                $db->query("INSERT INTO hicrm_order_details(order_id, order_product_id, order_product_quantity, order_product_price, order_product_vat_tax, order_product_discount) VALUE('".$id_oder."', '".$id_product."', '".$product[3]."', '".$product[4]."', '".$product[5]."', '".$product[6]."' )");

            }else{
                $db->query("INSERT INTO hicrm_category_products(cat_product_code, cat_product_name,cat_product_unit, cat_product_status, cat_product_parent) VALUE('".$product[0]."', '".$product[1]."', '".$product[2]."', '1','0') ");
                $db->query("SELECT * FROM hicrm_category_products WHERE cat_product_code = '".$product[0]."' LIMIT 1");
                $id_product = $db->fetch_object(true)->id;
				$db->query("INSERT INTO hicrm_order_details(order_id, order_product_id, order_product_quantity, order_product_price, order_product_vat_tax, order_product_discount) VALUE('".$id_oder."', '".$id_product."', '".$product[3]."', '".$product[4]."', '".$product[5]."', '".$product[6]."' )");
            }


        }

        $result['status'] = 200;
        $result['message'] = "Tạo đơn thành công!";
        echo json_encode($result);
    }
    public function autocompleteProductcode(){
        global $db;
        $result = array();
        if(isset($_GET['data']) && $_GET['data'] != ''){
            $db->query("SELECT * FROM hicrm_category_products WHERE cat_product_name LIKE '%".$_GET['data']."%' AND cat_product_status NOT IN (99)");
            $data_cat_pr = $db->fetch_object();
            foreach($data_cat_pr as $pr){
                array_push($result,  $pr->cat_product_code . " - ". $pr->cat_product_name);
            }
        }
        echo json_encode($result);
    }
	public function searchproduct()
	{
		$q = "m";
		
		global $db;
		$db->query("SELECT * FROM hicrm_category_products WHERE (cat_product_name LIKE '%".$q."%' OR cat_product_code LIKE '%".$q."%') AND NOT(cat_product_status = 99)");
		
		$products = $db->fetch_object();
		$result = array();
		$s = array();
		
		foreach($products as $p)
		{
			$sub = array();
			$sub["code"] = $p->cat_product_code;
			$sub["name"] = $p->cat_product_name;
			$sub["unit"] = $p->cat_product_unit;
			array_push($s,$sub);
		}
		$result["suggestions"]= $s;
		
		echo json_encode($result);
	}
	
	public function deleteEvent(){
		if(!$this->requireAdminApiPermission('events', false)){ return; }
		$id = $_POST['id'];
		$table = 'hicrm_events';
		$row = 'event_status';
		$this->model->action($id, $row, $table);
		$result['status'] = 200;
		echo json_encode($result);

	}
	public function orderGetBranch(){
		global $db;
		$db->query("SELECT * FROM hicrm_employees WHERE id = '".$_POST['order_employee_id']."'");
		$branch_id = $db->fetch_object(true)->employee_branch;
		$result = array();
		$db->query("SELECT * FROM hicrm_branchs WHERE id = '".$branch_id."'");
		$data = $db->fetch_object(true)->branch_name;
		$result['status'] = 200;
		$result['data'] = $data;
		echo json_encode($result);
	}
	public function orderGetPaymentPolicy(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_payment_policies WHERE id = '".$_POST['id_payment']."'");
		$data = $db->fetch_object(true)->policy_debt_day;
		$result['status'] = 200;
		$result['data'] = $data;
		echo json_encode($result);
	}
	public function getproductcodeorder(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_category_products WHERE cat_product_status NOT IN(99)");
		$data_cat_pr = $db->fetch_object();
		$data = '<option disabled selected="selected" >Chọn</option>';
			foreach($data_cat_pr as $row_product){
			$data .= '<option value="'.$row_product->id.'">'.$row_product->cat_product_code.'</option>';
			}
		$result['status'] = 200;
		$result['data'] = $data;
		echo json_encode($result);
	}
	public function getcategoryproductorder(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_category_products WHERE cat_product_status NOT IN(99)");
		$data_cat_pr = $db->fetch_object();
		$data = '	<option value="" disabled selected="selected">Chọn</option>
					<option value="0" >Danh mục gốc</option>';
		foreach($data_cat_pr as $row_cat_pr){
		$data .= '<option value="'. $row_cat_pr->id.'">'.$row_cat_pr->cat_product_name.'</option>';
		}	
		$result['status'] = 200;
		$result['data'] = $data;
		echo json_encode($result);		
	}
	//======================== END ORDER API =================================//
	
	//======================== WAREHOUSE API =================================//
	//					Function add warehouses	//
	public function warehouses(){
		global $db;
		$result = array();
		if($_POST['method'] == "new"){
			echo "INSERT INTO hicrm_warehouses(warehouse_code, warehouse_branch_id, warehouse_name,warehouse_description,warehouse_parent,warehouse_create_date,warehouse_status) VALUES ('".$_POST['warehouse_code']."','".$_POST['warehouse_branch_id']."','".$_POST['warehouse_name']."','".$_POST['warehouse_description']."','".$_POST['warehouse_parent']."','".date("Y-m-d H:i:s")."','1' )";
				$db->query("INSERT INTO hicrm_warehouses(warehouse_code, warehouse_branch_id, warehouse_name,warehouse_description,warehouse_parent,warehouse_create_date,warehouse_status) VALUES ('".$_POST['warehouse_code']."','".$_POST['warehouse_branch_id']."','".$_POST['warehouse_name']."','".$_POST['warehouse_description']."','".$_POST['warehouse_parent']."','".date("Y-m-d H:i:s")."','1' )");
				$result['message'] = "Thêm thành công";
				$result['status'] = 200;
		}elseif($_POST['method'] == "update"){
			
			$db->query("SELECT * FROM hicrm_warehouses WHERE id = '".$_POST['id']."'");
			
			$db->fetch_object(true);
				if($db->num_row()){
						$db->query("UPDATE hicrm_warehouses SET warehouse_code='".$_POST['warehouse_code']."',warehouse_branch_id='".$_POST['warehouse_branch_id']."',warehouse_name='".$_POST['warehouse_name']."',warehouse_description='".$_POST['warehouse_description']."',warehouse_parent='".$_POST['warehouse_parent']."' WHERE id='".$_POST['id']."'");
						$result['message'] = "Sửa thành công";
						$result['status'] = 200;
					
				}else{
					$result['message'] = "Không tìm  khoản mục chi phí này";
					$result['status'] = 500;
				}
				
		}
		echo json_encode($result);
	}
	public function addrowtableorder(){
		global $db;
		$result = array();
		$row = $_POST['rowcount'];
		$db->query("SELECT * FROM hicrm_category_products WHERE cat_product_status NOT IN(99)");
		$category_product = $db->fetch_object();
		$db->query("SELECT * FROM hicrm_units WHERE unit_status NOT IN(99)"); 
		$units = $db->fetch_object();
		$data ='
		 <tr></td>
		<td> 
            <div class="box-product-code">
            <input type="text" class="form-control product_code" id="product_code_'.$row.'" placeholder="Mã sản phẩm" autocomplete="off">
            </div>
            
                
            </td>
            <td>
                <input value="" class="form-control cat_product_name"  name="cat_product_name" id="cat_product_name_id_'.$row.'"  />
            </td>
            <td>
				<select name="product_unit_id" id="product_unit_id'.$row.'" class="select select2 product_unit_id">';
									foreach($units as $unit){ 
									$data.='<option value=" '.$unit->id.'">'.$unit->unit_name.'</option>';
								 }
				$data.='</select>
            </td>
            <td>
                <input value="" class="form-control product_quantity" type="number" min="0" id="product_quantity_'.$row.'" name="product_quantity">
            </td>
            <td>
                <input value="" class="form-control product_price"  name="product_price" id="product_price_'.$row.'"/>
            </td>
            <td>
                <input value="" class="form-control product_into_money"  name="product_into_money" id="product_into_money_'.$row.'" readonly/>
            </td>
            <td>
                <select class="select select2withsearch product_vat_tax"  name="product_vat_tax" id="product_vat_tax'.$row.'">
                <option value="0">0</option>
                <option value="5">5</option>
                <option value="10">10</option>
                <option value="0">KCT</option>
                
                </select>
            </td>
            <td>
                <input value="" class="product_money_vat_tax form-control" id="product_money_vat_tax_'.$row.'" readonly/>
            </td>
            <td>
            <input value="0" type="number" class=" form-control product_discount" id="product_discount_'.$row.'"  />
          </td>
		</tr>';
		 $result['data'] = $data;
		 $result['status'] = 200;
		echo json_encode($result);
	}
	public function action_warehouses(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_warehouses WHERE id = '".$_POST['id']."'");
		$db->fetch_object(true);
		if($db->num_row()){
			if($_POST['method'] == "active"){
				if($_POST['warehouse_status'] == 1){
					$db->query("UPDATE hicrm_warehouses SET warehouse_status = 2 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Đã ngừng hoạt động";
				}else{
					$db->query("UPDATE hicrm_warehouses SET warehouse_status = 1 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Đã hoạt động";
				}
			}elseif($_POST['method'] == "delete"){
					$db->query("UPDATE hicrm_warehouses SET warehouse_status = 99 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Xóa thành công";
			}else{
				$result['message'] = "Không tìm thấy tác vụ thực hiện";
				$result['status'] = 500;
			}
		}else{
			$result['message'] = "Lỗi hệ thống. Không tìm khoản mục chi phí này";
			$result['status'] = 500;
		}
		echo json_encode($result);
	}
	//					END Function add warehouses	//
	//					Function add branchs	//
	public function addBranchs(){
		
		global $db;
		$result = array();
		$db->query("INSERT INTO hicrm_branchs(branch_uid, branch_tax_code, branch_name, branch_address, branch_phone, branch_email, branch_director, branch_type, branch_founded_date, branch_created_date) VALUES ('".$_SESSION['user']['id']."','".$_POST['branch_tax_code']."','".$_POST['branch_name']."','".$_POST['branch_address']."','".$_POST['branch_phone']."','".$_POST['branch_email']."','".$_POST['branch_director']."','1','".$_POST['branch_founded_date']."','".date("Y-m-d H:i:s")."')");
		$result['status'] = 200;
		$result['message'] = "Thêm thành công";
		echo json_encode($result);
		
	}
	//					END Function add branchs	//
	//======================== END ORDER API =================================//
	//					Function get Customer_ID	//
	public function orderCustomerid(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_customers WHERE id = '".$_POST['customer_id']."'");
		$row_customers = $db->fetch_object(true);
		$result['status'] = 200;
		$result['customer_address'] = $row_customers->customer_address;
		$result['customer_tax_code'] = $row_customers->customer_tax_code;
		$result['customer_phone'] = $row_customers->customer_phone;
		$result['customer_email'] = $row_customers->customer_email;
		$result['customer_debt'] = number_format($row_customers->customer_debt,0);
		echo json_encode($result);
	}
	//					Function get Category_product	//
	public function orderCategoryproduct(){
		global $db;
		$result = array();
		
		$db->query("SELECT *, cp.id as cpid FROM hicrm_category_products as cp LEFT JOIN hicrm_units as u ON cp.cat_product_unit = u.id WHERE cp.id = '".$_POST['cat_pr_id']."'");
		$data_cat_product = $db->fetch_object(true);
		
		$cat_product_name = $data_cat_product->cat_product_name;
		$cat_product_unit = $data_cat_product->unit_name;
		$result['status'] = 200;
		$result['cat_product_name'] = $cat_product_name;
		$result['cat_product_unit'] = $cat_product_unit;
		echo json_encode($result);
	}
	//======================== Customer API =================================//
	//					Function Duplicate Customer 						 //
	public function duplicatecustomer()
	{
		global $db;
		$cid = $_POST['cid'];
		$customer_uid = $_SESSION['user']['id'];
		$result = array();
		
		$db->query("SELECT * FROM hicrm_customers ORDER BY id DESC LIMIT 1");
		$lastno = $db->fetch_object(true)->customer_code;
		$prefix = $this->helper->get_config("customer_prefix");
		//PREFIX1234567
		$lastno = substr($lastno,-7);
		$lastno = $lastno+1;
		$lastno = $prefix."".str_pad($lastno, 7, '0', STR_PAD_LEFT);
		$db->query("SELECT * FROM hicrm_customers WHERE id = '".$cid."' ORDER BY id DESC LIMIT 1");
		$oc = $db->fetch_object(true);
		$db->query("INSERT INTO hicrm_customers(customer_uid,customer_code,customer_tax_code,customer_name,customer_title,customer_address,customer_phone,customer_email,customer_group,customer_type,customer_is_vendor,customer_staff,customer_note,customer_payment_policy,customer_debit,customer_credit,customer_created_date,customer_status) VALUES('".$customer_uid."','".$lastno."','".$oc->customer_tax_code."','".$oc->customer_name."','".$oc->customer_title."','".$oc->customer_address."','".$oc->customer_phone."','".$oc->customer_email."','".$oc->customer_group."','".$oc->customer_type."','".$oc->customer_is_vendor."','".$oc->customer_staff."','".$oc->customer_note."','".$oc->customer_payment_policy."','".$oc->customer_debit."','".$oc->customer_credit."','".date("Y-m-d H:i:s")."','".$oc->customer_status."')");
		$result["status"] = 200;
		$result["new_customer_code"] = $lastno;
		echo json_encode($result);
	}
	public function deletecustomer()
	{
		global $db;
		$cid = $_POST['cid'];
		$result = array();
		$db->query("UPDATE hicrm_customers SET customer_status = 99 WHERE id = '".$cid."'");
		$result["status"] = 200;
		echo json_encode($result);
	}
	public function deactivecustomer()
	{
		global $db;
		$cid = $_POST['cid'];
		$result = array();
		$db->query("UPDATE hicrm_customers SET customer_status = 2 WHERE id = '".$cid."'");
		$result["status"] = 200;
		echo json_encode($result);
	}
	public function activecustomer()
	{
		global $db;
		$cid = $_POST['cid'];
		$result = array();
		$db->query("UPDATE hicrm_customers SET customer_status = 1 WHERE id = '".$cid."'");
		$result["status"] = 200;
		echo json_encode($result);
	}
	
	//======================== Customer API =================================//
	//======================== Income API =================================//
	public function addnewincomerow()
	{
		global $db;
		$result = array();
		$row = $_POST['rowcount'];
		$db->query("SELECT * FROM hicrm_accounts WHERE account_status = 1 ORDER BY id ASC");
		$accounts = $db->fetch_object();
		$data = '<tr class="data-row-2">
		  <td>
			 <input type="text" id="income_detail_description_'.$row.'" class="form-control">
		  </td>
		  <td>
			 <select id="income_detail_credit_'.$row.'" class="select select2withsearch">';
				foreach($accounts as $account)
				{
					$data .= '<option value="'.$account->account_number.'" >'.$account->account_number.' - '.$account->account_name.'</option>';
				
				}
			$data .= '
		   </select>
		  </td>
		  <td>
			 <select id="income_detail_debit_'.$row.'" class="select select2withsearch">';
			 
				foreach($accounts as $account)
				{
					$data .= '<option value="'.$account->account_number.'" >'.$account->account_number.' - '.$account->account_name.'</option>';
				
				}
		   $data .= '</select>
		  </td>
		  <td>
			 <input id="income_detail_amount_'.$row.'" type="text" class="form-control row-amount">
		  </td>
		  <td class="add-remove text-end">
			 <i data-row="2" class="fas fa-plus-circle btn-add-row"></i > <i class="fas fa-minus-circle btn-remove-row"></i>
		  </td>
	   </tr>';
	   $result["status"] = 200;
	   $result["data"] = $data;
	   echo json_encode($result);
	}
	public function newincome()
	{
		global $db;
		//array(8) { ["income_type"]=> string(1) "1" ["income_code"]=> string(10) "ALI0000001" ["income_to"]=> string(1) "7" ["income_account_date"]=> string(10) "23-08-2021" ["income_create_date"]=> string(10) "23-08-2021" ["income_staff"]=> string(1) "4" ["income_note"]=> string(12) "Test phiếu" ["income_document"]=> string(1) "2" }
		$db->query("INSERT INTO hicrm_incomes(income_no,income_type,income_created_date,income_accounting_date,income_to,income_note,income_staff,income_document,income_status,income_created_by) VALUES('".$_POST['income_code']."','".$_POST['income_type']."','".date("Y-m-d H:i:s",strtotime($_POST['income_create_date']))."','".date("Y-m-d H:i:s",strtotime($_POST['income_account_date']))."','".$_POST['income_to']."','".$_POST['income_note']."','".$_POST['income_staff']."','".$_POST['income_document']."','0','".$_SESSION['user']['id']."')");
		$db->query("SELECT id FROM hicrm_incomes WHERE income_no = '".$_POST['income_code']."' ORDER BY id DESC LIMIT 1");
		$incomeid = $db->fetch_object(true)->id;
		foreach($_POST['details'] as $detail)
		{
			$db->query("INSERT INTO hicrm_income_details(income_id,income_detail,income_debit,income_credit,income_amount) VALUES('".$incomeid."','".$detail[0]."','".$detail[1]."','".$detail[2]."','".$detail[3]."')");
		}
		//var_dump($_POST);
	}
	//======================== Income API =================================//
	//======================== Template API =================================//
	public function gettemplatedetail()
	{
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_templates WHERE id = '".$_POST['id']."'ORDER BY id DESC LIMIT 1");
		$template = $db->fetch_object(true);
		$result["html"] = $template->template_html;
		echo json_encode($result);
	}
	public function updatetemplate()
	{
		global $db;
		$result = array();
		$db->query("UPDATE hicrm_templates SET template_html = '".$_POST['html']."' WHERE id = '".$_POST['id']."'");
		$result["status"] = 200;
		echo json_encode($result);
	}
	//======================== Template API =================================//
	//login eOffice
	public function editCustomer(){
		global $db;
		
		$result = array();
		if(isset($_POST['id']) && $_POST['id'] != ''){
			$db->query("UPDATE hicrm_customers SET customer_tax_code='".$_POST['customer_tax_code']."',customer_name='".$_POST['customer_name']."',customer_title='".$_POST['customer_title']."',customer_address='".$_POST['customer_address']."',customer_phone='".$_POST['customer_phone']."',customer_email='".$_POST['customer_email']."',customer_group='".$_POST['customer_group']."',customer_type='".$_POST['customer_type']."',customer_is_vendor='".$_POST['customer_is_vendor']."',customer_staff='".$_POST['customer_staff']."',customer_note='".$_POST['customer_note']."',customer_last_update='".date("Y-m-d H:i:s")."' WHERE id = '".$_POST['id']."'");
			$result['status'] = 200;
			$result['message'] = "Sửa thành công";
			$result['return_url'] = XC_URL."/app/customers";
		}else{
			$result['status'] = 200;
			$result['message'] = "Không tìm thấy khách hàng/NCC này!";
		}
		echo json_encode($result);
	}
	public function addCustomer()
	{
		global $db;
		$customer_code = $_POST['customer_code'];
		$customer_tax_code = $_POST['customer_tax_code'];
		$customer_title = $_POST['customer_title'];
		$customer_name = $_POST['customer_name'];
		$customer_note = $_POST['customer_note'];
		$customer_address = $_POST['customer_address'];
		$customer_email = $_POST['customer_email'];
		$customer_phone = $_POST['customer_phone']; 
		$customer_is_vendor = $_POST['customer_is_vendor'];
		$customer_staff = $_POST['customer_staff'];
		$customer_type = $_POST['customer_type'];
		$customer_group = 1;
		$customer_payment_policy = 1;
		$customer_debit = 1111;
		$customer_credit = 1111;
		$customer_created_date = $customer_last_update = date('Y-m-d H:i:s');
		$customer_status = 1;
		$customer_uid = $_SESSION['user']['id'];
		$result = array();
		
		$db->query("INSERT INTO hicrm_customers(customer_uid, customer_code, customer_tax_code, customer_name, customer_title, customer_address, customer_phone, customer_email, customer_group, customer_type, customer_is_vendor, customer_staff, customer_note, customer_payment_policy, customer_debit, customer_credit, customer_created_date, customer_last_update, customer_status) VALUES ('".$uid."','".$customer_code."','".$customer_tax_code."','".$customer_name."','".$customer_title."','".$customer_address."','".$customer_phone."','".$customer_email."','".$customer_group."','".$customer_type."','".$customer_is_vendor."','".$customer_staff."','".$customer_note."','".$customer_payment_policy."','".$customer_debit."','".$customer_credit."','".$customer_created_date."','".$customer_last_update."','".$customer_status."' ) ");
		$result['status'] = 200;
		echo json_encode($result);
	}
		//======================== USER API =================================//
	
	public function action_category_users(){
		global $db;
		$uid = $_POST['id'];
		$result = array();
		$db->query("SELECT * FROM hicrm_users WHERE id = '".$uid."'");
		$db->fetch_object(true);
		
		if($db->num_row()){
			if($_POST['method'] == 'active'){
				
				if($_POST['user_status'] == 1){
					$db->query("UPDATE hicrm_users SET user_status = 2 WHERE id = '".$uid."'");
					$result["message"] = "Đã ngưng hoạt động";
					$result["status"] = 200;
				}else{
					$db->query("UPDATE hicrm_users SET user_status = 1 WHERE id = '".$uid."'");
					$result["message"] = "Đã hoạt động";
					$result["status"] = 200;
				}
				
			}elseif($_POST['method'] == "role"){
					$result['status'] = 404;
					$result['message'] = "Tính năng đang nâng cấp";
			}elseif($_POST['method'] == 'delete'){
				var_dump('delete');
				$db->query("UPDATE hicrm_users SET user_status = 99 WHERE id = '".$uid."'");
				$result["message"] = "Xóa thành công";
				$result["status"] = 200;
			}else{
				$result["message"] = "Không tìm thấy tác vụ thực hiện!";
				$result["status"] = 500;
			}
			
		}else{
			$result["message"] = "Không tìm thấy tài khoản này!";
			$result["status"] = 500;
		}
		
		echo json_encode($result);
	}
	//end user
	
		//======================== Employees API =================================//
	//Employees
	public function addEmployee(){
		// echo 'sssssa';
		global $db;
		$employee_code = $_POST['employee_code'];
		$employee_name = $_POST['employee_name'];
		// $employee_branch = $_POST['employee_branch'];
		// $employee_position = $_POST['employee_position'];
		$employee_address = $_POST['employee_address'];
		$employee_birthday = $_POST['employee_birthday'];
		$employee_birthday = date('Y-m-d', strtotime($employee_birthday));
		$employee_gender = $_POST['employee_gender'];
		$employee_phone = $_POST['employee_phone'];
		$employee_national_id = $_POST['employee_national_id'];
		$employee_issue_date = $_POST['employee_issue_date'];
		$employee_issue_date = date("Y-m-d", strtotime($employee_issue_date));
		$employee_issue_by = $_POST['employee_issue_by'];
		$employee_email = $_POST['employee_email'];
		$employee_department = $_POST['employee_department'];
		$employee_debt = '0.0';
		$employee_des = $_POST['employee_des'];
		$employee_status = 1;
		$result = array();
		$employee_created_date = $employee_last_update = date('Y-m-d H:i:s');
		$default_image = 'doctor_default.png';
		$FinalFilenameFront = "";
		$FinalFilenameFront2 = "";
		$expensions = array("jpeg","jpg","png");
		// echo $_FILES['employee_image']['name'];
		//echo $_FILES['hinhanh']['name']."ssss";
		if(isset($_FILES['employee_image'])) {
			// Không có file upload → gán hình mặc định
			$FinalFilenameFront = $default_image;
	
			$errors= array();
			$file_name = $_FILES['employee_image']['name'];
			$file_size =$_FILES['employee_image']['size'];
			$file_tmp =$_FILES['employee_image']['tmp_name'];
			$file_type=$_FILES['employee_image']['type'];
			$file_ext=strtolower(end(explode('.',$_FILES['employee_image']['name'])));
			$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['employee_image']['name']); 
			$FinalFilenameFront = md5(time())."-".$FinalFilename;
			if(in_array($file_ext,$expensions)=== false){
				$errors[]="Vui lòng chọn file định dạng .png, .jpg.";
			}
			if($file_size > 5242880){
				$errors[]='Dung lượng file tối đa 2Mb';
			}
			if(empty($errors)==true){
				move_uploaded_file($file_tmp,"./uploads/doctors/".$FinalFilenameFront);
				
			}else
			{
				$result["status"] = 500;
			}
		}
		
		$db->query("INSERT INTO hicrm_employees(employee_code, employee_name, employee_gender, employee_birthday, employee_branch, employee_department, employee_position, employee_national_id, employee_issue_date, employee_issue_by, employee_address, employee_phone, employee_email,employee_debt,employee_image, employee_des,employee_status, employee_created_date, employee_last_update) VALUES('".$employee_code."','".$employee_name."','".$employee_gender."','".$employee_birthday."','".$employee_branch."','".$employee_department."','".$employee_position."','".$employee_national_id."','".$employee_issue_date."','".$employee_issue_by."','".$employee_address."','".$employee_phone."','".$employee_email."','".$employee_debt."','".$FinalFilenameFront."','".$employee_des."','".$employee_status."','".$employee_created_date."','".$employee_last_update."' )");
		$result['status'] = 200;
		$result["url"] = XC_URL."/uploads/doctors/".$FinalFilenameFront;
		$result["id"] = $FinalFilenameFront;
		echo json_encode($result);
	}
	public function updateEmployee(){
				
		global $db;
		$id = $_POST['employeeid'];
		$employee_code = $_POST['employee_code'];
		$employee_name = $_POST['employee_name'];
		// $employee_branch = $_POST['employee_branch'];
		// $employee_position = $_POST['employee_position'];
		$employee_address = $_POST['employee_address'];
		$employee_birthday = $_POST['employee_birthday'];
		$employee_birthday = date('Y-m-d', strtotime($employee_birthday));
		$employee_gender = $_POST['employee_gender'];
		$employee_phone = $_POST['employee_phone'];
		$employee_national_id = $_POST['employee_national_id'];
		$employee_issue_date = $_POST['employee_issue_date'];
		$employee_issue_date = date("Y-m-d", strtotime($employee_issue_date));
		$employee_issue_by = $_POST['employee_issue_by'];
		$employee_email = $_POST['employee_email'];
		$employee_department = $_POST['employee_department'];
		$employee_debt = '0.0';
		$employee_des =  $_POST['employee_des'];
		$employee_status = 1;
		$result = array();
		$employee_created_date = $employee_last_update = date('Y-m-d H:i:s');
		$expensions = array("jpeg","jpg","png");
		
		$db->query("SELECT * FROM hicrm_employees WHERE id = '".$id."'");
		if($db->num_row())
		{
		
			if(isset($_FILES['employee_image']))
			{
				$errors= array();
				$file_name = $_FILES['employee_image']['name'];
				$file_size =$_FILES['employee_image']['size'];
				$file_tmp =$_FILES['employee_image']['tmp_name'];
				$file_type=$_FILES['employee_image']['type'];
				$file_ext=strtolower(end(explode('.',$_FILES['employee_image']['name'])));
				$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['employee_image']['name']); 
				$FinalFilenameFront = md5(time())."-".$FinalFilename;
				if(in_array($file_ext,$expensions)=== false){
					$errors[]="Extension not allowed, please choose a .png, .jpg file.";
				}
				if($file_size > 5242880){
					$errors[]='File size must be max 2Mb';
				}
				if(empty($errors)==true){
					move_uploaded_file($file_tmp,"./uploads/doctors/".$FinalFilenameFront);
					
				}else
				{
					$result["status"] = 500;
				}
				
			}
			
				
		$updateimage = ($FinalFilenameFront != "")? ", employee_image = '".$FinalFilenameFront."'" : "";
		$db->query("UPDATE hicrm_employees SET employee_code='".$employee_code."',employee_name='".$employee_name."',employee_gender='".$employee_gender."',employee_birthday='".$employee_birthday."',employee_department='".$employee_department."',employee_national_id='".$employee_national_id."',employee_issue_date='".$employee_issue_date."',employee_issue_by='".$employee_issue_by."',employee_address='".$employee_address."',employee_phone='".$employee_phone."',employee_email='".$employee_email."',employee_debt = '".$employee_debt."'".$updateimage.",employee_des = '".$employee_des."', employee_status = '".$employee_status."',employee_created_date='".$employee_created_date."',employee_last_update='".$employee_last_update."' WHERE id = '".$id."'");
		// echo $kq;
		// echo "UPDATE hicrm_employees SET employee_code='".$employee_code."',employee_name='".$employee_name."',employee_gender='".$employee_gender."',employee_birthday='".$employee_birthday."',employee_branch='".$employee_branch."',employee_department='".$employee_department."',employee_position='".$employee_position."',employee_national_id='".$employee_national_id."',employee_issue_date='".$employee_issue_date."',employee_issue_by='".$employee_issue_by."',employee_address='".$employee_address."',employee_phone='".$employee_phone."',employee_email='".$employee_email."',employee_debt = '".$employee_debt."'".$updateimage.",employee_des = '".$employee_des."', employee_status = '".$employee_status."',employee_created_date='".	$employee_created_date."',employee_last_update='".$employee_last_update."' WHERE id = '".$id."'";
		$result['status'] = 200;
		$result["url"] = XC_URL."/uploads/doctors/".$FinalFilenameFront;
		echo json_encode($result);
		}
		
		
	}
	public function duplicateEmployee()
	{
		global $db;
		$eid = $_POST['eid'];
		$result = array();
		$db->query("SELECT * FROM hicrm_employees ORDER BY id DESC LIMIT 1");
		$lastno = $db->fetch_object(true)->employee_code;
		$prefix = $this->helper->get_config("employee_prefix");
		//PREFIX1234567
		$lastno = substr($lastno,-7);
		$lastno = $lastno+1;
		$lastno = $prefix."".str_pad($lastno, 7, '0', STR_PAD_LEFT);
		$db->query("SELECT * FROM hicrm_employees WHERE id = '".$eid."' ORDER BY id DESC LIMIT 1");
		$oe = $db->fetch_object(true);
		$employee_created_date = $employee_last_update = date("Y:m:d H:i:s");
		
		$db->query("INSERT INTO hicrm_employees(employee_code, employee_name, employee_gender, employee_birthday, employee_branch, employee_department, employee_position, employee_national_id, employee_issue_date, employee_issue_by, employee_address, employee_phone, employee_email,employee_debt, employee_status, employee_created_date, employee_last_update) VALUES('".$lastno."','".$oe->employee_name."','".$oe->employee_gender."','".$oe->employee_birthday."','".$oe->employee_branch."','".$oe->employee_department."','".$oe->employee_position."','".$oe->employee_national_id."','".$oe->employee_issue_date."','".$oe->employee_issue_by."','".$oe->employee_address."','".$oe->employee_phone."','".$oe->employee_email."','".$oe->employee_debt."','".$oe->employee_status."','".$employee_created_date."','".$employee_last_update."' )");
		$result["status"] = 200;
		$result["new_employee_code"] = $lastno;
		echo json_encode($result);
	}
	
	public function deleteEmployee()
	{
		global $db;
		$eid = $_POST['eid'];
		$result = array();
		$db->query("SELECT * FROM hicrm_employees WHERE id = '".$eid."'");
		$db->fetch_object(true);
		if($db->num_row()){
			$db->query("UPDATE hicrm_employees SET employee_status = 99 WHERE id = '".$eid."'");
			
			$result["status"] = 200;
		}else{
			$result['message'] = "Không tìm thấy nhân viên này";
			$result["status"] = 500;
		}
		
		echo json_encode($result);
	}
	//end employee
	//======================== Categories API =================================//
	//Selected compobox edit_accounts
	public function cpbaccounts(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_accounts WHERE account_status = 1");
		$accounts = $db->fetch_object();
		$db->query("SELECT * FROM hicrm_accounts WHERE id = '".$_POST['id']."'");
		$row_account = $db->fetch_object(true);
		
		$data = '<option value = ""> Chọn loại </option>';
		
		foreach($accounts as $account){
			if($_POST['method'] == 'update'){
			$isSelected = ($account->id == $row_account->account_parent) ? "selected" : "" ;
			$data .= '<option value = "'.$account->id.'" '.$isSelected.'> '.$account->account_number.' - '.$account->account_name.' </option>';
			}else{
				
				$data .= '
				
				<option value = "'.$account->id.'"> '.$account->account_number.' - '.$account->account_name.' </option>';
			}
			
		}
		
		
			$result['status'] = 200;
			$result['data'] =  $data;
		echo json_encode($result);
		
	}
	
	//end
	//Category expense items
	public function category_expenseitems(){
		global $db;
		if($_POST['expense_parent'] != ''){
			$expense_parent = $_POST['expense_parent'];
		}else{
			$expense_parent = 0;
		}
		$result = array();
		if($_POST['method'] == "new"){
			$db->query("SELECT * FROM hicrm_expense_items WHERE expense_code = '".$_POST['expense_code']."' AND expense_status NOT IN(99) ");
			$db->fetch_object(true);
			if($db->num_row()){
				$result['message'] = "Mã khoản mục chi phí đã tồn tại";
				$result['status'] = 500;
			}else{
			
				$db->query("INSERT INTO hicrm_expense_items(expense_code, expense_name,expense_description, expense_parent,expense_status) VALUES ('".$_POST['expense_code']."','".$_POST['expense_name']."','".$_POST['expense_description']."','".$expense_parent."','1' )");
				$result['message'] = "Thêm thành công";
				$result['status'] = 200;
			}
		}elseif($_POST['method'] == "update"){
			
			$db->query("SELECT * FROM hicrm_expense_items WHERE id = '".$_POST['id']."'");
			
			$db->fetch_object(true);
				if($db->num_row()){
					
						$db->query("UPDATE hicrm_expense_items SET expense_code='".$_POST['expense_code']."',expense_name='".$_POST['expense_name']."',expense_description='".$_POST['expense_description']."',expense_parent='".$expense_parent."' WHERE id='".$_POST['id']."'");
						$result['message'] = "Sửa thành công";
						$result['status'] = 200;
					
				}else{
					$result['message'] = "Không tìm  khoản mục chi phí này";
					$result['status'] = 500;
				}
				
		}
		echo json_encode($result);
	}
	public function action_expenseitems(){
		global $db;
		//var_dump($_POST);
		$result = array();
		$db->query("SELECT * FROM hicrm_expense_items WHERE id = '".$_POST['id']."'");
		$db->fetch_object(true);
		if($db->num_row()){
			if($_POST['method'] == "active"){
				if($_POST['expense_status'] == 1){
					$db->query("UPDATE hicrm_expense_items SET expense_status = 2 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Đã ngừng hoạt động";
				}else{
					$db->query("UPDATE hicrm_expense_items SET expense_status = 1 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Đã hoạt động";
				}
			}elseif($_POST['method'] == "delete"){
					$db->query("UPDATE hicrm_expense_items SET expense_status = 99 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Xóa thành công";
			}else{
				$result['message'] = "Không tìm thấy tác vụ thực hiện";
				$result['status'] = 500;
			}
		}else{
			$result['message'] = "Lỗi hệ thống. Không tìm khoản mục chi phí này";
			$result['status'] = 500;
		}
		echo json_encode($result);
	}
	public function cpbExpenseitem(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_expense_items WHERE expense_status NOT IN(99)");
		$row_expenses = $db->fetch_object();
		$data = '<option value="" disabled selected="selected" >Chọn </option>
					<option value="0">Mục gốc </option>';
			foreach($row_expenses as $row){
				if($_POST['method'] == 'update'){
					$isSelected = ($_POST['expense_parent'] == $row->id) ? 'selected="selected" ' : '';
					$data .= '<option value="'.$row->id.'" '.$isSelected.'>'.$row->expense_code.' - '.$row->expense_name.' </option>';
				}else{
					$data .= '<option value="'.$row->id.'">'.$row->expense_code.' - '.$row->expense_name.' </option>';
				}
			}
		
		$result['status'] = 200;
		$result['data'] = $data;
		echo json_encode($result);
	}
	//end expense
	//api show category product when edit category product
	public function cpbCategoryproduct(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_category_products WHERE cat_product_status NOT IN(99)");
		$row_cat_pr = $db->fetch_object();
		$db->query("SELECT * FROM hicrm_units WHERE unit_status NOT IN(99)");
		$cat_product_units = $db->fetch_object();
		$data_category = '<option disabled selected="selected" >Chọn danh mục </option>
					<option value="0">Danh mục gốc  </option>';
		$data_unit = '<option value="" disabled selected="selected" >Lựa chọn</option>';
			foreach($cat_product_units as $row_unit){
				if($_POST['method'] == 'update'){
					
				$isSelected = ($_POST['cat_product_unit'] == $row_unit->id) ? 'selected="selected" ' : '';
				$data_unit .= '<option value="'.$row_unit->id.'" '.$isSelected.'>'.$row_unit->unit_code.' - '.$row_unit->unit_name.' </option>';
				}else{
					$data_unit .= '<option value="'.$row_unit->id.'" >'.$row_unit->unit_code.' - '.$row_unit->unit_name.' </option>';
				}
			}
			foreach($row_cat_pr as $row){
				if($_POST['method'] == 'update'){
				$isSelected = ($_POST['cat_product_parent'] == $row->id) ? 'selected="selected" ' : '';
				$data_category .= '<option value="'.$row->id.'" '.$isSelected.'>'.$row->cat_product_code.' - '.$row->cat_product_name.' </option>';
				}else{
					$data_category .= '<option value="'.$row->id.'">'.$row->cat_product_code.' - '.$row->cat_product_name.' </option>';
				}
			}
		
		$result['status'] = 200;
		$result['data_category'] = $data_category;
		$result['data_unit'] = $data_unit;
		echo json_encode($result);
	}
	//end
	//Category template Type
	public function category_template_types(){
		if(isset($_POST['template_type_debt']) && $_POST['template_type_debt'] != ''){
			$template_type_debt = $_POST['template_type_debt'];
		}else{
			$template_type_debt = 0;
		}
		if(isset($_POST['template_type_to']) && $_POST['template_type_to'] != ''){
			$template_type_to = $_POST['template_type_to'];
		}else{
			$template_type_to = 0;
		}
		global $db;
		$result = array();
		if($_POST['method'] == "new"){
			$db->query("SELECT * FROM hicrm_template_types WHERE template_type_code = '".$_POST['template_type_code']."' AND template_type_status = '1' ");
			$db->fetch_object(true);
			if($db->num_row()){
				$result['message'] = "Mã loại chứng từ đã tồn tại";
				$result['status'] = 500;
			}else{
			
				$db->query("INSERT INTO hicrm_template_types(template_type_code, template_type_name,template_type_description, template_type_status) VALUES ('".$_POST['template_type_code']."','".$_POST['template_type_name']."','".$_POST['template_type_description']."','1' )");
				$result['message'] = "Thêm thành công";
				$result['status'] = 200;
			}
		}elseif($_POST['method'] == "duplicate")
		{
			$db->query("SELECT * FROM hicrm_template_types WHERE id = '".$_POST['id']."'");
			$db->fetch_object(true);
				if($db->num_row()){
					$db->query("SELECT * FROM hicrm_template_types WHERE template_type_code = '".$_POST['template_type_code']."' AND template_type_status NOT IN(99) ");
					$db->fetch_object(true);
					if($db->num_row()){
						$result['message'] = "Mã loại chứng từ đã tồn tại";
						$result['status'] = 500;
					}else{
						$db->query("INSERT INTO hicrm_template_types(template_type_code, template_type_name, template_type_description, template_type_status) VALUES ('".$_POST['template_type_code']."','".$_POST['template_type_name']."','".$_POST['template_type_description']."','1' )");
						$result['message'] = "Nhân bản thành công";
						$result['status'] = 200;
					}
					
				}else{
					$result['message'] = "Không tìm thấy loại chứng từ này này";
					$result['status'] = 500;
				}
		}elseif($_POST['method'] == "update"){
			
			$db->query("SELECT * FROM hicrm_template_types WHERE id = '".$_POST['id']."'");
			
			$db->fetch_object(true);
				if($db->num_row()){
					
						$db->query("UPDATE hicrm_template_types SET template_type_code='".$_POST['template_type_code']."',template_type_name='".$_POST['template_type_name']."',template_type_description='".$_POST['template_type_description']."' WHERE id='".$_POST['id']."'");
						$result['message'] = "Sửa thành công";
						$result['status'] = 200;
					
				}else{
					$result['message'] = "Không tìm thấy thấy loại chứng từ này";
					$result['status'] = 500;
				}
				
		}
		echo json_encode($result);
	}
	public function action_template_types(){
		global $db;
		//var_dump($_POST);
		$result = array();
		$db->query("SELECT * FROM hicrm_template_types WHERE id = '".$_POST['id']."'");
		$db->fetch_object(true);
		if($db->num_row()){
			if($_POST['method'] == "active"){
				if($_POST['template_type_status'] == 1){
					$db->query("UPDATE hicrm_template_types SET template_type_status = 2 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Đã ngừng hoạt động";
				}else{
					$db->query("UPDATE hicrm_template_types SET template_type_status = 1 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Đã hoạt động";
				}
			}elseif($_POST['method'] == "delete"){
					$db->query("UPDATE hicrm_template_types SET template_type_status = 99 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Xóa thành công";
			}else{
				$result['message'] = "Không tìm thấy tác vụ thực hiện";
				$result['status'] = 500;
			}
		}else{
			$result['message'] = "Lỗi hệ thống. Không tìm loại chứng từ này";
			$result['status'] = 500;
		}
		echo json_encode($result);
	}
	
	//end
	//Category payment policies
	public function category_payment_policies(){
		global $db;
		if(isset($_POST['policy_comission']) && $_POST['policy_comission'] != ''){
			$policy_comission = $_POST['policy_comission'];
		}else{
			$policy_comission = 0.0;
		}
		$result = array();
		if($_POST['method'] == "new"){
			$db->query("SELECT * FROM hicrm_payment_policies WHERE policy_code = '".$_POST['policy_code']."' AND policy_status = '1' ");
			$db->fetch_object(true);
			if($db->num_row()){
				$result['message'] = "Mã điều khoản thanh toán đã tồn tại";
				$result['status'] = 500;
			}else{
				
				$db->query("INSERT INTO hicrm_payment_policies(policy_uid,policy_code, policy_title, policy_debt_day, policy_comission, policy_status) VALUES ('".$_SESSION['user']['id']."','".$_POST['policy_code']."','".$_POST['policy_title']."','".$_POST['policy_debt_day']."','".$policy_comission."','1' )");
				$result['message'] = "Thêm thành công";
				$result['status'] = 200;
			}
		}elseif($_POST['method'] == "duplicate"){
			$db->query("SELECT * FROM hicrm_payment_policies WHERE id = '".$_POST['id']."'");
			$db->fetch_object(true);
				if($db->num_row()){
					$db->query("SELECT * FROM hicrm_payment_policies WHERE policy_code = '".$_POST['policy_code']."' AND policy_status NOT IN(99) ");
					$db->fetch_object(true);
					if($db->num_row()){
						$result['message'] = "Mã điều khoản thanh toán đã tồn tại";
						$result['status'] = 500;
					}else{
						$db->query("INSERT INTO hicrm_payment_policies(policy_uid,policy_code, policy_title, policy_debt_day, policy_comission, policy_status) VALUES ('".$_SESSION['user']['id']."','".$_POST['policy_code']."','".$_POST['policy_title']."','".$_POST['policy_debt_day']."','".$policy_comission."','1' )");
						$result['message'] = "Nhân bản thành công";
						$result['status'] = 200;
					}
					
				}else{
					$result['message'] = "Không tìm thấy điều khoản thanh toán này";
					$result['status'] = 500;
				}
		}elseif($_POST['method'] == "update"){
			$db->query("SELECT * FROM hicrm_payment_policies WHERE id = '".$_POST['id']."'");
			$db->fetch_object(true);
				if($db->num_row()){
						$db->query("UPDATE hicrm_payment_policies SET  policy_uid='".$_SESSION['user']['id']."', policy_code='".$_POST['policy_code']."',policy_title='".$_POST['policy_title']."',policy_debt_day='".$_POST['policy_debt_day']."',policy_comission='".$policy_comission."',policy_status='1' WHERE id = '".$_POST['id']."'");
						$result['message'] = "Sửa thành công";
						$result['status'] = 200;
					
				}else{
					$result['message'] = "Không tìm thấy thấy điều khoản thanh toán này";
					$result['status'] = 500;
				}
				
		}
		echo json_encode($result);
	}
	public function action_payment_policies(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_payment_policies WHERE id = '".$_POST['id']."'");
		$db->fetch_object(true);
		if($db->num_row()){
			if($_POST['method'] == "active"){
				if($_POST['policy_status'] == 1){
					$db->query("UPDATE hicrm_payment_policies SET policy_status = 2 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Đã ngừng hoạt động";
				}else{
					$db->query("UPDATE hicrm_payment_policies SET policy_status = 1 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Đã hoạt động";
				}
			}elseif($_POST['method'] == "delete"){
					$db->query("UPDATE hicrm_payment_policies SET policy_status = 99 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Xóa thành công";
			}else{
				$result['message'] = "Không tìm thấy tác vụ thực hiện";
				$result['status'] = 500;
			}
		}else{
			$result['message'] = "Lỗi hệ thống. Không tìm điều khoản thanh toán này";
			$result['status'] = 500;
		}
		echo json_encode($result);
	}
	//end
	//Category_spend_collectes
	public function category_spend_collectes(){
		
		global $db;
		if(isset($_POST['spend_collecte_active']) && $_POST['spend_collecte_active'] != ""){
			$spend_collecte_active = 1;
		}else{
			$spend_collecte_active = 0;
		}
		$result = array();
		if($_POST['method'] == "new"){
			$db->query("SELECT * FROM hicrm_spend_collectes WHERE spend_collecte_code = '".$_POST['spend_collecte_code']."' AND spend_collecte_status = '1' ");
			$db->fetch_object(true);
			if($db->num_row()){
				$result['message'] = "Mã Thu/Chi đã tồn tại";
				$result['status'] = 500;
			}else{
				
				$db->query("INSERT INTO hicrm_spend_collectes(spend_collecte_code, spend_collecte_name, spend_collecte_type, spend_collecte_active, spend_collecte_parent, spend_collecte_description, spend_collecte_status) VALUES ('".$_POST['spend_collecte_code']."','".$_POST['spend_collecte_name']."','".$_POST['spend_collecte_type']."','".$_POST['spend_collecte_active']."','".$_POST['spend_collecte_parent']."','".$_POST['spend_collecte_description']."','1' )");
				$result['message'] = "Thêm thành công";
				$result['status'] = 200;
			}
		}elseif($_POST['method'] == "duplicate"){
			$db->query("SELECT * FROM hicrm_spend_collectes WHERE id = '".$_POST['id']."'");
			$db->fetch_object(true);
				if($db->num_row()){
					$db->query("SELECT * FROM hicrm_spend_collectes WHERE spend_collecte_code = '".$_POST['spend_collecte_code']."' AND spend_collecte_status NOT IN(99) ");
					$db->fetch_object(true);
					if($db->num_row()){
						$result['message'] = "Mã Thu/Chi đã tồn tại";
						$result['status'] = 500;
					}else{
						$db->query("INSERT INTO hicrm_spend_collectes(spend_collecte_code, spend_collecte_name, spend_collecte_type, spend_collecte_active, spend_collecte_parent, spend_collecte_description, spend_collecte_status) VALUES ('".$_POST['spend_collecte_code']."','".$_POST['spend_collecte_name']."','".$_POST['spend_collecte_type']."','".$spend_collecte_active."','".$_POST['spend_collecte_parent']."','".$_POST['spend_collecte_description']."','1' )");
						$result['message'] = "Nhân bản thành công";
						$result['status'] = 200;
					}
					
				}else{
					$result['message'] = "Không tìm thấy Thu/Chi này";
					$result['status'] = 500;
				}
		}elseif($_POST['method'] == "update"){
			$db->query("SELECT * FROM hicrm_spend_collectes WHERE id = '".$_POST['id']."'");
			$db->fetch_object(true);
				if($db->num_row()){
						$db->query("UPDATE hicrm_spend_collectes SET spend_collecte_code='".$_POST['spend_collecte_code']."',spend_collecte_name='".$_POST['spend_collecte_name']."',spend_collecte_type='".$_POST['spend_collecte_type']."',spend_collecte_active='".$spend_collecte_active."',spend_collecte_parent='".$_POST['spend_collecte_parent']."',spend_collecte_description='".$_POST['spend_collecte_description']."' WHERE id = '".$_POST['id']."'");
						$result['message'] = "Sửa thành công";
						$result['status'] = 200;
					
				}else{
					$result['message'] = "Không tìm thấy Thu/Chi này";
					$result['status'] = 500;
				}
				
		}
		echo json_encode($result);
	}
	public function action_spend_collectes(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_spend_collectes WHERE id = '".$_POST['id']."'");
		$db->fetch_object(true);
		if($db->num_row()){
			if($_POST['method'] == "active"){
				if($_POST['spend_collecte_status'] == 1){
					$db->query("UPDATE hicrm_spend_collectes SET spend_collecte_status = 2 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Đã ngừng hoạt động";
				}else{
					$db->query("UPDATE hicrm_spend_collectes SET spend_collecte_status = 1 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Đã hoạt động";
				}
			}elseif($_POST['method'] == "delete"){
					$db->query("UPDATE hicrm_spend_collectes SET spend_collecte_status = 99 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Xóa thành công";
			}else{
				$result['message'] = "Không tìm thấy tác vụ thực hiện";
				$result['status'] = 500;
			}
		}else{
			$result['message'] = "Lỗi hệ thống. Không tìm Thu/Chi này";
			$result['status'] = 500;
		}
		echo json_encode($result);
	}
	public function cpbSpendcollecte(){
		global $db;
		$db->query("SELECT * FROM hicrm_spend_collectes WHERE spend_collecte_status NOT IN(99)");
		$row_spend_collectes = $db->fetch_object();
		$data = '<option value="" disabled selected="selected" >Lựa chọn </option>';
		$result = array();
		foreach($row_spend_collectes as $row){
			if($_POST['method'] == 'update' || $_POST['method'] == 'duplicate' ){
				$isSelected = ($_POST['spend_collecte_parent'] == $row->id) ? 'selected="selected"' : '';
					
				$data .= '<option value="'.$row->id.'" '.$isSelected.' > '.$row->spend_collecte_code.' - '.$row->spend_collecte_name.' </option>';
				
				$result['status'] = 200;
				$result['data'] = $data;
			}elseif($_POST['method'] == 'new'){
				$data .= '<option value="'.$row->id.'"  > '.$row->spend_collecte_code.' - '.$row->spend_collecte_name.' </option>';
				$result['status'] = 200;
				$result['data'] = $data;
			}else{
				$result['status'] = 404;
				$result['message'] = "Dữ liệu trống";
			}
		}
		echo json_encode($result);
		
		
	}
	//end
	//Category_product
	public function category_products(){
		global $db;
		$result = array();
		if($_POST['method'] == "new"){
			$db->query("SELECT * FROM hicrm_category_products WHERE cat_product_code = '".$_POST['cat_product_code']."' AND cat_product_status = '1' ");
			$db->fetch_object(true);
			if($db->num_row()){
				$result['message'] = "Mã danh mục sản phẩm đã tồn tại";
				$result['status'] = 500;
			}else{
				$db->query("INSERT INTO hicrm_category_products(cat_product_code, cat_product_name,cat_product_unit,cat_product_description, cat_product_status, cat_product_parent) VALUES ('".$_POST['cat_product_code']."','".$_POST['cat_product_name']."','".$_POST['cat_product_unit']."','".$_POST['cat_product_description']."','1','".$_POST['cat_product_parent']."' )");
				$result['message'] = "Thêm thành công";
				$result['status'] = 200;
			}
		}elseif($_POST['method'] == "duplicate"){
			$db->query("SELECT * FROM hicrm_category_products WHERE id = '".$_POST['id']."'");
			$db->fetch_object(true);
				if($db->num_row()){
					$db->query("SELECT * FROM hicrm_category_products WHERE cat_product_code = '".$_POST['cat_product_code']."' AND cat_product_status NOT IN(99) ");
					$db->fetch_object(true);
					if($db->num_row()){
						$result['message'] = "Mã danh mục sản phẩm đã tồn tại";
						$result['status'] = 500;
					}else{
						$db->query("INSERT INTO hicrm_category_products(cat_product_code, cat_product_name, cat_product_unit, cat_product_description, cat_product_status, cat_product_parent) VALUES ('".$_POST['cat_product_code']."','".$_POST['cat_product_name']."','".$_POST['cat_product_unit']."','".$_POST['cat_product_description']."','1','".$_POST['cat_product_parent']."' )");
						$result['message'] = "Nhân bản thành công";
						$result['status'] = 200;
					}
					
				}else{
					$result['message'] = "Không tìm thấy danh mục sản phẩm này";
					$result['status'] = 500;
				}
		}elseif($_POST['method'] == "update"){
			$db->query("SELECT * FROM hicrm_category_products WHERE id = '".$_POST['id']."'");
			$db->fetch_object(true);
				if($db->num_row()){
						$db->query("UPDATE hicrm_category_products SET cat_product_code='".$_POST['cat_product_code']."',cat_product_name='".$_POST['cat_product_name']."',cat_product_unit='".$_POST['cat_product_unit']."',cat_product_description='".$_POST['cat_product_description']."',cat_product_parent='".$_POST['cat_product_parent']."' WHERE id = '".$_POST['id']."'");
						$result['message'] = "Sửa thành công";
						$result['status'] = 200;
					
				}else{
					$result['message'] = "Không tìm thấy danh mục sản phẩm này";
					$result['status'] = 500;
				}
				
		}
		echo json_encode($result);
		
	}
	public function action_products(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_category_products WHERE id = '".$_POST['id']."'");
		$db->fetch_object(true);
		if($db->num_row()){
			if($_POST['method'] == "active"){
				if($_POST['cat_product_status'] == 1){
					$db->query("UPDATE hicrm_category_products SET cat_product_status = 2 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Đã ngừng hoạt động";
				}else{
					$db->query("UPDATE hicrm_category_products SET cat_product_status = 1 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Đã hoạt động";
				}
			}elseif($_POST['method'] == "delete"){
					$db->query("UPDATE hicrm_category_products SET cat_product_status = 99 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Xóa thành công";
			}else{
				$result['message'] = "Không tìm thấy tác vụ thực hiện";
				$result['status'] = 500;
			}
		}else{
			$result['message'] = "Lỗi hệ thống. Không tìm danh mục sản phẩm này";
			$result['status'] = 500;
		}
		echo json_encode($result);
	}
	//Currency
	public function category_currencies(){
		global $db;
		var_dump($_POST);
		$result = array();
		if($_POST['method'] == "new"){
			$db->query("SELECT * FROM hicrm_currencies WHERE currency_code = '".$_POST['currency_code']."' AND currency_status = '1' ");
			$db->fetch_object(true);
			if($db->num_row()){
				$result['message'] = "Mã loại tiền đã tồn tại";
				$result['status'] = 500;
			}else{
				$db->query("INSERT INTO hicrm_currencies(currency_code, currency_name, currency_rate, currency_type, currency_status) VALUES ('".$_POST['currency_code']."','".$_POST['currency_name']."','".$_POST['currency_rate']."','".$_POST['currency_type']."','1' )");
				$result['message'] = "Thêm thành công";
				$result['status'] = 200;
			}
		}elseif($_POST['method'] == "update"){
			$db->query("SELECT * FROM hicrm_currencies WHERE id = '".$_POST['id']."'");
			$db->fetch_object(true);
				if($db->num_row()){
						$db->query("UPDATE hicrm_currencies SET currency_code='".$_POST['currency_code']."',currency_name='".$_POST['currency_name']."',currency_rate='".$_POST['currency_rate']."',currency_type='".$_POST['currency_type']."' WHERE id = '".$_POST['id']."'");
						$result['message'] = "Sửa thành công";
						$result['status'] = 200;
				}else{
					$result['message'] = "Không tìm thấy đơn vị tính này";
					$result['status'] = 500;
				}
				
		}
		echo json_encode($result);
	}
	public function action_currencies(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_currencies WHERE id = '".$_POST['id']."'");
		$db->fetch_object(true);
		if($db->num_row()){
			if($_POST['method'] == "active"){
				if($_POST['currency_status'] == 1){
					$db->query("UPDATE hicrm_currencies SET currency_status = 2 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Đã ngừng hoạt động";
				}else{
					$db->query("UPDATE hicrm_currencies SET currency_status = 1 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Đã hoạt động";
				}
			}elseif($_POST['method'] == "delete"){
					$db->query("UPDATE hicrm_currencies SET currency_status = 99 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Xóa thành công";
			}else{
				$result['message'] = "Không tìm thấy tác vụ thực hiện";
				$result['status'] = 500;
			}
		}else{
			$result['message'] = "Lỗi hệ thống. Không tìm thấy tài khoản ngân hàng";
			$result['status'] = 500;
		}
		echo json_encode($result);
	}
	public function cpbCurrency(){
		global $db;
		$db->query("SELECT * FROM hicrm_accounts WHERE account_status NOT IN(99)");
		$row_accounts = $db->fetch_object();
		$data = '<option value="" disabled selected="selected" >Lựa chọn </option>';
		$result = array();
		$currency_type = $_POST['currency_type'];
		foreach($row_accounts as $row){
			if($_POST['method'] == 'update'){
				$isSelected = ($currency_type == $row->id) ? 'selected="selected"' : '';
					
				$data .= '<option value="'.$row->id.'" '.$isSelected.' > '.$row->account_number.' - '.$row->account_name.' </option>';
				
				$result['status'] = 200;
				$result['data'] = $data;
			}elseif($_POST['method'] == 'new'){
				$data .= '<option value="'.$row->id.'"  > '.$row->account_number.' - '.$row->account_name.' </option>';
				$result['status'] = 200;
				$result['data'] = $data;
			}else{
				$result['status'] = 404;
				$result['message'] = "Dữ liệu trống";
			}
		}
		echo json_encode($result);
		
		
	}
	//end
	//Units
	public function category_units(){
		global $db;
		$result = array();
		if($_POST['method'] == "new"){
			$db->query("SELECT * FROM hicrm_units WHERE unit_code = '".$_POST['unit_code']."' AND unit_status = '1' ");
			$db->fetch_object(true);
			if($db->num_row()){
				$result['message'] = "Mã đơn vị tính đã tồn tại";
				$result['status'] = 500;
			}else{
				$db->query("INSERT INTO hicrm_units(unit_code, unit_name, unit_description, unit_status) VALUES ('".$_POST['unit_code']."','".$_POST['unit_name']."','".$_POST['unit_description']."','1' )");
				$result['message'] = "Thêm thành công";
				$result['status'] = 200;
			}
		}elseif($_POST['method'] == "update"){
			$db->query("SELECT * FROM hicrm_units WHERE id = '".$_POST['id']."'");
			$db->fetch_object(true);
				if($db->num_row()){
						$db->query("UPDATE hicrm_units SET unit_code='".$_POST['unit_code']."',unit_name='".$_POST['unit_name']."',unit_description='".$_POST['unit_description']."',unit_status='1' WHERE id = '".$_POST['id']."'");
						$result['message'] = "Sửa thành công";
						$result['status'] = 200;
					
				}else{
					$result['message'] = "Không tìm thấy đơn vị tính này";
					$result['status'] = 500;
				}
		}
		echo json_encode($result);
	}
	public function action_units(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_units WHERE id = '".$_POST['id']."'");
		$db->fetch_object(true);
		if($db->num_row()){
			if($_POST['method'] == "active"){
				if($_POST['unit_status'] == 1){
					$db->query("UPDATE hicrm_units SET unit_status = 2 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Đã ngừng hoạt động";
				}else{
					$db->query("UPDATE hicrm_units SET unit_status = 1 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Đã hoạt động";
				}
			}elseif($_POST['method'] == "delete"){
					$db->query("UPDATE hicrm_units SET unit_status = 99 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Xóa thành công";
			}else{
				$result['message'] = "Không tìm thấy tác vụ thực hiện";
				$result['status'] = 500;
			}
		}else{
			$result['message'] = "Lỗi hệ thống. Không tìm thấy tài khoản ngân hàng";
			$result['status'] = 500;
		}
		echo json_encode($result);
	}
	//End Units
	//Bankaccounts
	public function category_bankaccounts(){
		global $db;
		$result = array();
		
		if($_POST['method'] == "new"){
			$db->query("SELECT * FROM hicrm_bank_accounts WHERE ba_account = '".$_POST['ba_account']."' AND ba_status = '1' ");
			$db->fetch_object(true);
			if($db->num_row()){
				$result['message'] = "Số tài khoản ngân hàng đã tồn tại";
				$result['status'] = 500;
			}else{
				$db->query("INSERT INTO hicrm_bank_accounts(bank_id, ba_account,ba_holder,ba_branch,ba_description,ba_status,ba_primary) VALUES('".$_POST['bank_id']."','".$_POST['ba_account']."','".$_POST['ba_holder']."','".$_POST['ba_branch']."','".$_POST['ba_description']."', '1','0')");
				$result['message'] = "Thêm thành công";
				$result['status'] = 200;
			}
		}elseif($_POST['method'] == "duplicate"){
			$db->query("SELECT * FROM hicrm_bank_accounts WHERE id = '".$_POST['id']."'");
			$db->fetch_object(true);
				if($db->num_row()){
					$db->query("SELECT * FROM hicrm_bank_accounts WHERE ba_account = '".$_POST['ba_account']."' AND ba_status = NOT IN(99) ");
					$db->fetch_object(true);
					if($db->num_row()){
						$result['message'] = "Số tài khoản ngân hàng đã tồn tại";
						$result['status'] = 500;
					}else{
						$db->query("INSERT INTO hicrm_bank_accounts(bank_id, ba_account,ba_holder,ba_branch,ba_description,ba_status,ba_primary) VALUES('".$_POST['bank_id']."','".$_POST['ba_account']."','".$_POST['ba_holder']."','".$_POST['ba_branch']."','".$_POST['ba_description']."', '1','0')");
						$result['message'] = "Nhân bản thành công";
						$result['status'] = 200;
					}
					
				}else{
					$result['message'] = "Không tìm thấy tài khoản ngân hàng này";
					$result['status'] = 500;
				}
		}elseif($_POST['method'] == "update"){
			$db->query("SELECT * FROM hicrm_bank_accounts WHERE id = '".$_POST['id']."'");
			$db->fetch_object(true);
				if($db->num_row()){
					$db->query("UPDATE hicrm_bank_accounts SET bank_id = '".$_POST['bank_id']."',ba_account = '".$_POST['ba_account']."',ba_holder = '".$_POST['ba_holder']."',ba_branch = '".$_POST['ba_branch']."',ba_description = '".$_POST['ba_description']."' WHERE id = '".$_POST['id']."'");
					$result['message'] = "Sửa thành công";
					$result['status'] = 200;
				}else{
					$result['message'] = "Không tìm thấy tài khoản ngân hàng này";
					$result['status'] = 500;
				}
		}else{
				$result['message'] = "Không tìm thấy tác vụ thực hiện";
				$result['status'] = 500;
			}
		
		echo json_encode($result);
	}
	public function action_bankaccounts(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_bank_accounts WHERE id = '".$_POST['id']."'");
		$db->fetch_object(true);
		if($db->num_row()){
			if($_POST['method'] == "active"){
				if($_POST['ba_status'] == 1){
					$db->query("UPDATE hicrm_bank_accounts SET ba_status = 2 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Đã ngừng hoạt động";
				}else{
					$db->query("UPDATE hicrm_bank_accounts SET ba_status = 1 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Đã hoạt động";
				}
			}elseif($_POST['method'] == "delete"){
					$db->query("UPDATE hicrm_bank_accounts SET ba_status = 99 WHERE id = '".$_POST['id']."'");
					$result['status'] = 200;
					$result['message'] = "Xóa thành công";
			}else{
				$result['message'] = "Không tìm thấy tác vụ thực hiện";
				$result['status'] = 500;
			}
		}else{
			$result['message'] = "Lỗi hệ thống. Không tìm thấy tài khoản ngân hàng";
			$result['status'] = 500;
		}
		echo json_encode($result);
	}
	//api combobox Ten ngan hang muc EDIT
	public function cmpAccountbank(){
		global $db;
		$db->query("SELECT * FROM hicrm_banks WHERE bank_status NOT IN(99)");
		$row_banks = $db->fetch_object();
		$data = '<option disabled selected="selected"> Chọn ngân hàng </option>';
		$result = array();
		foreach($row_banks as $bank){
			if($_POST['method'] == 'update'){
				$bank_name = ($bank->bank_code) ? $bank->bank_code ."&nbsp;-&nbsp;".$bank->bank_name : "" .$bank->bank_name; 
				$isSelected = ($_POST['bankid'] == $bank->id) ? 'selected="selected"' : '';
					
				$data .= '<option value="'.$bank->id.'" '.$isSelected.' > '.$bank_name.' </option>';
			}elseif($_POST['method'] == 'new'){
				$bank_name = ($bank->bank_code) ? $bank->bank_code ."&nbsp;-&nbsp;".$bank->bank_name : "" .$bank->bank_name; 
				$data .= '<option value="'.$bank->id.'" > '.$bank_name.' </option>';
			}elseif($_POST['method'] == 'duplicate'){
				$bank_name = ($bank->bank_code) ? $bank->bank_code ."&nbsp;-&nbsp;".$bank->bank_name : "" .$bank->bank_name; 
				$isSelected = ($_POST['bankid'] == $bank->id) ? 'selected="selected"' : '';
					
				$data .= '<option value="'.$bank->id.'" '.$isSelected.' > '.$bank_name.' </option>';
			}else{
				$result['status'] = 200;
				$result['message'] = "Dữ liệu trống";
			}
		}
			$result['status'] = 200;
			$result['data'] = $data;
			echo json_encode($result);
	}
	//end Bankaccounts
	//Departments
	public function category_departments(){
		global $db;
		$result = array();
		
		if($_POST['method'] == "new"){
			$db->query("SELECT * FROM hicrm_departments WHERE depart_name = '".$_POST['department_name']."' AND depart_status = '1' ");
			$db->fetch_object(true);
			if($db->num_row()){
				$result['message'] = "Tên phòng ban đã tồn tại";
				$result['status'] = 500;
			}else{
				$db->query("INSERT INTO hicrm_departments(depart_name, depart_status) VALUES('".$_POST['department_name']."', '1')");
				$result['message'] = "Thêm thành công";
				$result['status'] = 200;
			}
		}elseif($_POST['method'] == "update"){
			$db->query("SELECT * FROM hicrm_departments WHERE id = '".$_POST['id']."'");
			$db->fetch_object(true);
				if($db->num_row()){
					$db->query("UPDATE hicrm_departments SET depart_name = '".$_POST['department_name']."' WHERE id = '".$_POST['id']."'");
					$result['message'] = "Sửa thành công";
					$result['status'] = 200;
				}else{
					$result['message'] = "Không tìm thấy phòng ban này";
					$result['status'] = 500;
				}
		}else{
				$result['message'] = "Không tìm thấy tác vụ thực hiện";
				$result['status'] = 500;
			}
		
		echo json_encode($result);
	}
	public function action_departments(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_departments WHERE id = '".$_POST['id']."'");
		$db->fetch_object(true);
		if($db->num_row()){
			if($_POST['method'] == 'delete'){
				$db->query("UPDATE hicrm_departments SET depart_status = '99' WHERE id = '".$_POST['id']."'");
				$result['message'] = "Xóa thành công";
				$result['status'] = 200;
			}else{
				$result['message'] = "Không tìm thấy tác vụ thực hiện";
				$result['status'] = 500;
			}
		}else{
			$result['message'] = "Không tìm thấy phòng ban này";
			$result['status'] = 500;
		}
		echo json_encode($result);
	}
	public function getdepartdata()
	{
		global $db;
		$result = array();
		$id = $_POST['id'];
		$db->query("SELECT * FROM hicrm_departments WHERE id = '".$id."' ORDER BY id DESC LIMIT 1");
		if($db->num_row())
		{
			$depart = $db->fetch_object(true);
			$result["data"]["name"] = $depart->depart_name;
			$result["status"] = 200;
		}
		else
		{
			$result["status"] = 404;
			$result["message"] = "Không tìm thấy phòng ban này!";
		}
		echo json_encode($result);
	}
	// end Departments
	//Customers group
	public function customergroup(){
		global $db;
		$cgid = $_POST['gid'];
		$method = $_POST['method'];
		$group_name = $_POST['group_name'];
		$group_code = $_POST['group_code'];
		$group_color = $_POST['group_color'];
		$group_description = $_POST['group_description'];
		$group_status = 1;
		$result = array();
		if($method == 'new'){
			$db->query("SELECT * FROM hicrm_customer_groups WHERE group_code = '".$group_code."'");
			$db->fetch_object(true);
			if($db->num_row()){
				$result['message'] = "Đã tồn tại mã khách hàng";
				$result['status'] = 500;
			}else{
				$db->query("INSERT INTO hicrm_customer_groups(group_code, group_name, group_color, group_description, group_status) VALUES ('".$group_code ."','".$group_name."','".$group_color."','".$group_description."','".$group_status."') ");
				$result["message"] = "Thêm thành công";
				$result['status'] = 200;
			}
		}elseif($method == "update"){
				$db->query("SELECT * FROM hicrm_customer_groups WHERE id = '".$cgid."'");
				$db->fetch_object(true);
				if($db->num_row()){
					$db->query("UPDATE hicrm_customer_groups SET group_code='".$group_code."',group_name='".$group_name."',group_color='".$group_color."',group_description='".$group_description."',group_status='".$group_status."' WHERE id = '".$cgid."'");
					$result["message"] = "Cập nhật thành công";
					$result['status'] = 200;
				}else{
					$result['message'] = " Không tồn tại khách hàng";
					$result['status'] = 500;
				}
		}elseif($method == 'delete'){
				$db->query("SELECT * FROM hicrm_customer_groups WHERE id = '".$cgid."'");
				$db->fetch_object(true);
				if($db->num_row()){
					$db->query("UPDATE hicrm_customer_groups SET group_status = 99 WHERE id = '".$cgid."'");
					$result['message'] = "Xóa thành công";
					$result["status"] = 200;
				}else{
					$result['message'] = " Không tồn tại khách hàng";
					$result['status'] = 500;
				}
		}else{
			$result['message'] = " Lỗi hệ thống";
			$result['status'] = 500;
		}
		echo json_encode($result);
	}
	public function actioncustomergroup(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_customer_groups WHERE id = '".$_POST['gid']."'");
		$db->fetch_object(true);
		if($db->num_row()){
			if($_POST['method'] == "active"){
				if($_POST['group_status'] == 1){
					$db->query("UPDATE hicrm_customer_groups SET group_status = 2 WHERE id = '".$_POST['gid']."'");
					$result['status'] = 200;
					$result['message'] = "Đã ngừng hoạt động";
				}else{
					$db->query("UPDATE hicrm_customer_groups SET group_status = 1 WHERE id = '".$_POST['gid']."'");
					$result['status'] = 200;
					$result['message'] = "Đã hoạt động";
				}
			}elseif($_POST['method'] == "delete"){
					$db->query("UPDATE hicrm_customer_groups SET group_status = 99 WHERE id = '".$_POST['gid']."'");
					$result['status'] = 200;
					$result['message'] = "Xóa thành công";
			}else{
				$result['message'] = "Không tìm thấy tác vụ thực hiện";
				$result['status'] = 500;
			}
		}else{
			$result['message'] = "Lỗi hệ thống";
			$result['status'] = 500;
		}
		echo json_encode($result);

	}
	
	//========================  Accounts =================================//
	public function category_accounts(){
		global $db;
		$method = $_POST['method'];
		$id = $_POST['aid'];
		
		$result = array();
	if($method == "new"){
		$db->query("SELECT * FROM hicrm_accounts WHERE account_number = '".$_POST['account_number']."'");
		$db->fetch_object(true);
		if($db->num_row()){
			$result['message'] = "Số tài khoản đã tồn tại";
			$result['status'] = 500;
			
		}else{
			$db->query("INSERT INTO hicrm_accounts(account_number, account_name, account_type, account_name_en, account_description, account_status, account_parent) VALUES ('".$_POST['account_number']."','".$_POST['account_name']."','".$_POST['account_type']."','".$_POST['account_name_en']."','".$_POST['account_description']."','1','".$_POST['account_parent']."')");
			$result['message'] = "Thêm thành công";
			$result['status'] = 200;
		}
	}elseif($method == "update"){
		$db->query("SELECT * FROM hicrm_accounts WHERE id = '".$id."'");
		$db->fetch_object(true);
		if($db->num_row()){
			$db->query("UPDATE hicrm_accounts SET account_number='".$_POST['account_number']."',account_name='".$_POST['account_name']."',account_type='".$_POST['account_type']."',account_name_en='".$_POST['account_name_en']."',account_description='".$_POST['account_description']."',account_status='1',account_parent='".$_POST['account_parent']."' WHERE id = '".$id."'");
			$result['message'] = "Sửa thành công";
			$result['status'] = 200;
		}else{
			$result['message'] = "Số tài khoản không tồn tại";
			$result['status'] = 500;
		}
	}else{
		$result['status'] = 404;
		$result['message'] = "Không tìm thấy tác vụ thực hiện";
	}
	echo json_encode($result);
	}
	public function actionaccount(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_accounts WHERE id = '".$_POST['aid']."'");
		$db->fetch_object(true);
		if($db->num_row()){
			if($_POST['method'] == 'active'){
				if($_POST['account_status'] == 1){
					$db->query("UPDATE hicrm_accounts SET account_status = 2 WHERE id = '".$_POST['aid']."'");
					$result['message'] = "Đã ngừng hoạt động";
					$result['status'] = 200;
				}elseif($_POST['account_status'] == 2){
					$db->query("UPDATE hicrm_accounts SET account_status = 1 WHERE id = '".$_POST['aid']."'");
					$result['message'] = "Đã hoạt động";
					$result['status'] = 200;
				}else{
					$result['message'] = "Không tìm thấy tác vụ thực hiện";
					$result['status'] = 500;
				}
			}elseif($_POST['method'] == 'delete'){
				$db->query("UPDATE hicrm_accounts SET account_status = 99 WHERE id = '".$_POST['aid']."'");
				$result['message'] = "Xóa thành công";
				$result["status"] = 200;
			}else{
				$result['message'] = "Không tìm thấy tác vụ thực hiện";
				$result['status'] = 500;
			}
		}
		echo json_encode($result);
	}
	public function deleteaccount(){}
	
	
	
	//======================== End Accounts =================================//
	
	//======================== Supplies  =================================//
	public function category_supplies(){
		global $db;
		$method = $_POST['method'];
		$id = $_POST['id'];
		
		$result = array();
		if($method == "new"){
			$db->query("SELECT * FROM hicrm_supplies WHERE supplie_code = '".$_POST['supplie_code']."'");
			$db->fetch_object(true);
			if($db->num_row()){
				$result['message'] = "Mã nhóm đã tồn tại";
				$result['status'] = 500;
				
			}else{
				$db->query("INSERT INTO hicrm_supplies(supplie_code, supplie_name, supplie_status, supplie_parent) VALUES ('".$_POST['supplie_code']."','".$_POST['supplie_name']."','1','".$_POST['supplie_parent']."')");
				$result['message'] = "Thêm thành công";
				$result['status'] = 200;
			}
		}elseif($method == "duplicate"){
			$db->query("SELECT * FROM hicrm_supplies WHERE supplie_code = '".$_POST['supplie_code']."'");
			$db->fetch_object(true);
			if($db->num_row()){
				$result['message'] = "Mã nhóm đã tồn tại";
				$result['status'] = 500;
				
			}else{
				$db->query("INSERT INTO hicrm_supplies(supplie_code, supplie_name, supplie_status, supplie_parent) VALUES ('".$_POST['supplie_code']."','".$_POST['supplie_name']."','1','".$_POST['supplie_parent']."')");
				$result['message'] = "Nhân bản thành công";
				$result['status'] = 200;
			}
		}elseif($method == "update"){
			$db->query("SELECT * FROM hicrm_supplies WHERE id = '".$id."'");
			$db->fetch_object(true);
				if($db->num_row()){
					$db->query("UPDATE hicrm_supplies SET supplie_code='".$_POST['supplie_code']."',supplie_name='".$_POST['supplie_name']."',supplie_status='1',supplie_parent='".$_POST['supplie_parent']."' WHERE id = '".$id."'");
					$result['message'] = "Sửa thành công";
					$result['status'] = 200;
				}else{
					$result['message'] = "Số tài khoản không tồn tại";
					$result['status'] = 500;
				}
		}else{
			$result['status'] = 404;
			$result['message'] = "Không tìm thấy tác vụ thực hiện";
		}
	echo json_encode($result);
	}
	public function action_supplies(){
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_supplies WHERE id = '".$_POST['id']."'");
		$db->fetch_object(true);
		if($db->num_row()){
			if($_POST['method'] == 'active'){
				if($_POST['supplie_status'] == 1){
					$db->query("UPDATE hicrm_supplies SET supplie_status = 2 WHERE id = '".$_POST['id']."'");
					$result['message'] = "Đã ngừng hoạt động";
					$result['status'] = 200;
				}elseif($_POST['supplie_status'] == 2){
					$db->query("UPDATE hicrm_supplies SET supplie_status = 1 WHERE id = '".$_POST['id']."'");
					$result['message'] = "Đã hoạt động";
					$result['status'] = 200;
				}else{
					$result['message'] = "Không tìm thấy tác vụ thực hiện";
					$result['status'] = 500;
				}
			}elseif($_POST['method'] == 'delete'){
				$db->query("UPDATE hicrm_supplies SET supplie_status = 99 WHERE id = '".$_POST['id']."'");
				$result['message'] = "Xóa thành công";
				$result["status"] = 200;
			}else{
				$result['message'] = "Không tìm thấy tác vụ thực hiện";
				$result['status'] = 500;
			}
		}else{
			$result['message'] = "Không tồn tại nhóm vật tư";
			$result['status'] = 500;
		}
		echo json_encode($result);
	}
	//======================== END Supplies  =================================//
	//======================== End Categories API =================================//
	//======================== Bookings API =================================//
	public function addbookings(){
		
		$event_assign_to = implode(",",$_POST['event_assign_to']);
		global $db;
		$result = array();
		$event_time_from = date("Y-m-d H:i:s", strtotime($_POST['event_from']));
		$event_time_to = date("Y-m-d H:i:s", strtotime($_POST['event_to']));
		
		$db->query("INSERT INTO hicrm_bookings(event_created_by, event_assign_to, event_host, event_time_from, event_time_to, event_title, event_description, event_type) VALUES ('".$_SESSION['user']['id']."', '".$event_assign_to."', '".$_POST['event_host']."', '".$event_time_from."', '".$event_time_to."', '".$_POST['event_title']."', '".$_POST['event_description']."', '".$_POST['event_type']."') ");
		$result['message'] = "Đặt lịch thành công";
		$result['status'] = 200;
		echo json_encode($result);
	}
	//======================== END Bookings API =================================//
	public function filterCustomer(){
		global $db;
		$keywork = $_POST['keywork'];
		$group_customer = $_POST['group_customer'];
		$status_customer = $_POST['status_customer'];
		if($keywork != ''){
			$db->query("SELECT * FROM  hicrm_customers WHERE customer_name LIKE '%".$keyword."%' OR customer_code LIKE '%".$keyword."%' OR customer_phone LIKE '%".$keyword."%' OR customer_email LIKE '%".$keyword."%'");
			
		}
		if(isset($group_customer) && $group_customer != 0){
			$customer_group = $_GET['customer_group'];
			$db->query("SELECT * FROM  hicrm_customers WHERE customer_group = '".$customer_group."'");
			return true;
		}
		if(isset($status_customer) && $status_customer != 0){
			$customer_status = $_GET['customer_status'];
			$db->query(" SELECT * FROM  hicrm_customers WHERE customer_status = '".$customer_status."'");
			return true;
		}
		
	}
	public function login()
	{
		global $db;
		$result = array();
		$loginContext = isset($_POST['login_context']) ? trim((string)$_POST['login_context']) : '';
		$isFrontendLogin = $loginContext === 'frontend';
		$email = $db->escapestring(isset($_POST["email"]) ? trim($_POST["email"]) : '');
		$password = $db->escapestring(isset($_POST["password"]) ? trim($_POST["password"]) : '');
		if($email === '' || $password === ''){
			$result["status"] = 500;
			$result['message'] = 'Vui lòng nhập email và mật khẩu';
			echo json_encode($result);
			return;
		}
		$password = md5($password);
		$db->query("SELECT u.*
			FROM hicrm_users u
			INNER JOIN hicrm_user_groups g ON u.user_group = g.id AND g.group_status NOT IN(99)
			WHERE (u.user_email = '".$email."' OR u.user_username = '".$email."')
				AND u.user_password = '".$password."'
				AND u.user_status = 1
			LIMIT 1");
		
        if($db->num_row())
        {
            $row = $db->fetch_object(true);
			$this->adminClearTwoFactorSession();
			unset($_SESSION['user']);
			unset($_SESSION['LoggedIn']);

			if($this->frontendUserRequiresVerification($row)){
				$this->frontendStorePendingVerification($row);
				$result["status"] = 200;
				$result["requires_verification"] = true;
				$result["message"] = "Tài khoản của bạn chưa xác thực email.";
				$result["user_id"] = intval($row->id);
				$result["email"] = trim((string)$row->user_email);
				$result["full_name"] = trim((string)$row->full_name);
				$result["return_url"] = $this->frontendCurrentBaseUrl()."/tai-khoan-chua-xac-thuc.html";
				echo json_encode($result);
				return;
			}

			$this->frontendClearPendingVerification();
			if($isFrontendLogin){
				$userGroup = intval(isset($row->user_group) ? $row->user_group : 0);
				if($userGroup === 1){
					$result["status"] = 403;
					$result["message"] = "Tài khoản quản trị không thể đăng nhập tại giao diện trang chủ. Vui lòng đăng nhập tại trang quản trị.";
					echo json_encode($result);
					return;
				}
				if(!in_array($userGroup, array(2, 3, 4), true)){
					$result["status"] = 403;
					$result["message"] = "Tài khoản này không được hỗ trợ đăng nhập tại giao diện trang chủ.";
					echo json_encode($result);
					return;
				}
			}
			if(!$isFrontendLogin && !$this->adminAccountHasAccess(intval($row->user_group))){
				$result["status"] = 403;
				$result["message"] = "Tài khoản chưa được cấp quyền truy cập trang quản trị.";
				echo json_encode($result);
				return;
			}
			if(intval($row->user_group) === 1 && $this->adminTwoFactorConfigEnabled()){
				$otpResult = $this->adminStartTwoFactorSession($row);
				if(!$otpResult['status']){
					$result["status"] = 500;
					$result['message'] = $otpResult['message'];
				}else{
					$result["status"] = 202;
					$result["require_2fa"] = true;
					$result["message"] = "Mã xác thực đã được gửi về email của bạn. Mã có hiệu lực trong 2 phút.";
					$result["expires_in"] = $this->adminGetTwoFactorRemainingSeconds();
				}
				echo json_encode($result);
				return;
			}

			$this->adminSetLoggedInSession($row);
			$result["status"] = 200;
			$result["name"] = $_SESSION['user']['full_name'];
			$result["message"] = "Đăng nhập thành công";
			$result['return_url'] = $isFrontendLogin ? $this->frontendLoginReturnUrl($row) : XC_URL.'/admin';
        }
		else
		{
			$result["status"] = "500";
			$result['message'] = 'Thông tin tài khoản hoặc mật khẩu không chính xác';
		}
		echo json_encode($result);
	}
	public function resendVerificationEmail()
	{
		global $db;
		$result = array();
		$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
		$email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
		$pending = isset($_SESSION['frontend_pending_verification']) && is_array($_SESSION['frontend_pending_verification']) ? $_SESSION['frontend_pending_verification'] : array();

		if($user_id <= 0 && isset($pending['user_id'])){
			$user_id = intval($pending['user_id']);
		}
		if($email === '' && isset($pending['email'])){
			$email = trim((string)$pending['email']);
		}

		if($user_id > 0){
			$db->query("SELECT * FROM hicrm_users WHERE id = '".$user_id."' AND user_status NOT IN(99) LIMIT 1");
		}elseif($email !== ''){
			$db->query("SELECT * FROM hicrm_users WHERE user_email = '".$db->escapestring($email)."' AND user_status NOT IN(99) LIMIT 1");
		}else{
			$result['status'] = 400;
			$result['message'] = 'Không tìm thấy tài khoản cần gửi lại email xác thực.';
			echo json_encode($result);
			return;
		}

		if(!$db->num_row()){
			$result['status'] = 404;
			$result['message'] = 'Tài khoản không tồn tại hoặc đã bị vô hiệu hóa.';
			echo json_encode($result);
			return;
		}

		$user = $db->fetch_object(true);
		if(!$this->frontendUserRequiresVerification($user)){
			$this->frontendClearPendingVerification();
			$result['status'] = 200;
			$result['message'] = 'Tài khoản này đã được xác thực email.';
			echo json_encode($result);
			return;
		}

		$sendResult = $this->frontendSendVerificationEmail($user, true);
		if(!$sendResult['status']){
			$result['status'] = 500;
			$result['message'] = $sendResult['message'];
			echo json_encode($result);
			return;
		}

		$this->frontendStorePendingVerification($user);
		$result['status'] = 200;
		$result['message'] = $sendResult['message'];
		$result['email'] = $this->maskEmail($sendResult['email']);
		echo json_encode($result);
	}
	public function applyJob()
	{
		global $db;
		header('Content-Type: application/json; charset=utf-8');
		$result = array();
		if(!(isset($_SESSION['user']['id']) && intval($_SESSION['user']['id']) > 0)){
			$result['status'] = 401;
			$result['message'] = 'Vui lòng đăng nhập tài khoản để ứng tuyển.';
			echo json_encode($result);
			return;
		}
		$user_id = intval($_SESSION['user']['id']);
		$job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
		if($job_id <= 0){
			$result['status'] = 400;
			$result['message'] = 'Tin tuyển dụng không hợp lệ.';
			echo json_encode($result);
			return;
		}

		$db->query("SELECT * FROM hicrm_users WHERE id = '".$user_id."' AND user_status = 1 LIMIT 1");
		if(!$db->num_row()){
			$result['status'] = 404;
			$result['message'] = 'Không tìm thấy tài khoản ứng tuyển.';
			echo json_encode($result);
			return;
		}
		$user = $db->fetch_object(true);
		$userGroup = intval(isset($user->user_group) ? $user->user_group : 0);
		if(!in_array($userGroup, array(3, 4), true)){
			$result['status'] = 403;
			$result['message'] = 'Chỉ tài khoản ứng viên mới có thể ứng tuyển việc làm.';
			echo json_encode($result);
			return;
		}
		if($this->frontendUserRequiresVerification($user)){
			$this->frontendStorePendingVerification($user);
			$result['status'] = 403;
			$result['requires_verification'] = true;
			$result['message'] = 'Tài khoản của bạn chưa được xác thực.';
			$result['return_url'] = $this->frontendCurrentBaseUrl()."/tai-khoan-chua-xac-thuc.html";
			echo json_encode($result);
			return;
		}

		$candidate = $this->frontendCandidateByUserId($user_id);
		if(!$candidate){
			$result['status'] = 403;
			$result['message'] = 'Tài khoản của bạn chưa được phê duyệt, vui lòng cập nhật hồ sơ đầy đủ.';
			echo json_encode($result);
			return;
		}
		if(intval(isset($candidate->status) ? $candidate->status : 0) !== 3){
			$result['status'] = 403;
			$result['message'] = 'Tài khoản của bạn chưa được phê duyệt, vui lòng cập nhật hồ sơ đầy đủ.';
			echo json_encode($result);
			return;
		}

		$db->query("SELECT * FROM hicrm_job_posts WHERE id = '".$job_id."' AND status = 'published' LIMIT 1");
		if(!$db->num_row()){
			$result['status'] = 404;
			$result['message'] = 'Bài đăng tuyển dụng không tồn tại hoặc đã ngừng nhận hồ sơ.';
			echo json_encode($result);
			return;
		}
		$job = $db->fetch_object(true);
		if(!empty($job->deadline)){
			$deadlineTs = strtotime((string)$job->deadline.' 23:59:59');
			if($deadlineTs && $deadlineTs < time()){
				$result['status'] = 410;
				$result['message'] = 'Bài đăng đã hết hạn nộp hồ sơ.';
				echo json_encode($result);
				return;
			}
		}

		$this->ensureJobApplicationsTable();
		$db->query("SELECT id FROM hicrm_job_applications WHERE candidate_id = '".intval($candidate->id)."' AND job_post_id = '".$job_id."' LIMIT 1");
		if($db->num_row()){
			$result['status'] = 409;
			$result['message'] = 'Bạn đã ứng tuyển công việc này trước đó.';
			echo json_encode($result);
			return;
		}

		$db->query("INSERT INTO hicrm_job_applications(candidate_id, user_id, job_post_id, employer_id, status, applied_at, created_at, updated_at)
			VALUES ('".intval($candidate->id)."', '".$user_id."', '".$job_id."', '".intval(isset($job->employer_id) ? $job->employer_id : 0)."', 'submitted', NOW(), NOW(), NOW())");

		$result['status'] = 200;
		$result['message'] = 'Ứng tuyển thành công.';
		$result['return_url'] = $this->frontendCurrentBaseUrl().'/quan-ly-ho-so-ung-vien.html';
		echo json_encode($result);
	}
	public function saveJobSupportRequest()
	{
		global $db;
		header('Content-Type: application/json; charset=utf-8');
		$result = array('status' => 400, 'message' => 'Dữ liệu gửi lên không hợp lệ.');

		if(!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string)$_SERVER['REQUEST_METHOD']) !== 'POST'){
			$result['status'] = 405;
			$result['message'] = 'Phương thức gửi dữ liệu không được hỗ trợ.';
			echo json_encode($result);
			return;
		}

		$csrfToken = isset($_POST['csrf_token']) ? trim((string)$_POST['csrf_token']) : '';
		$sessionToken = isset($_SESSION['job_support_csrf_token']) ? (string)$_SESSION['job_support_csrf_token'] : '';
		if($csrfToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $csrfToken)){
			$result['status'] = 403;
			$result['message'] = 'Phiên gửi thông tin không hợp lệ. Vui lòng tải lại trang.';
			echo json_encode($result);
			return;
		}

		$jobId = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
		$fullName = trim(preg_replace('/\s+/u', ' ', isset($_POST['full_name']) ? (string)$_POST['full_name'] : ''));
		$phoneInput = trim(isset($_POST['phone']) ? (string)$_POST['phone'] : '');
		$phone = preg_replace('/[^0-9+]/', '', $phoneInput);
		$email = trim(isset($_POST['email']) ? (string)$_POST['email'] : '');

		$errors = array();
		$nameLength = function_exists('mb_strlen') ? mb_strlen($fullName, 'UTF-8') : strlen($fullName);
		if($nameLength < 2 || $nameLength > 150){
			$errors['full_name'] = 'Họ và tên phải có từ 2 đến 150 ký tự.';
		}
		if(!preg_match('/^(?:\+84|0)[0-9]{8,10}$/', $phone)){
			$errors['phone'] = 'Số điện thoại không đúng định dạng.';
		}
		if(strlen($email) > 191 || !filter_var($email, FILTER_VALIDATE_EMAIL)){
			$errors['email'] = 'Email không đúng định dạng.';
		}
		if($jobId <= 0){
			$errors['job_id'] = 'Bài tuyển dụng không hợp lệ.';
		}
		if(!empty($errors)){
			$result['errors'] = $errors;
			$result['message'] = 'Vui lòng kiểm tra lại thông tin.';
			echo json_encode($result);
			return;
		}

		$db->query("SELECT id FROM hicrm_job_posts WHERE id = '".$jobId."' AND status = 'published' LIMIT 1");
		if(!$db->num_row()){
			$result['status'] = 404;
			$result['message'] = 'Bài tuyển dụng không tồn tại hoặc đã ngừng hiển thị.';
			echo json_encode($result);
			return;
		}

		$sessionId = session_id();
		if($sessionId === ''){
			$result['status'] = 403;
			$result['message'] = 'Không xác định được phiên truy cập. Vui lòng tải lại trang.';
			echo json_encode($result);
			return;
		}

		$sessionIdEsc = $db->escapestring(substr($sessionId, 0, 128));
		$db->query("SELECT id FROM hicrm_job_support_requests WHERE session_id = '".$sessionIdEsc."' LIMIT 1");
		if($db->num_row()){
			$result['status'] = 409;
			$result['message'] = 'Thông tin hỗ trợ đã được ghi nhận trong phiên này.';
			echo json_encode($result);
			return;
		}

		$ipAddress = isset($_SERVER['REMOTE_ADDR']) ? trim((string)$_SERVER['REMOTE_ADDR']) : '';
		$db->query("INSERT INTO hicrm_job_support_requests
			(job_id, full_name, phone, email, session_id, ip_address, created_at)
			VALUES
			('".$jobId."', '".$db->escapestring($fullName)."', '".$db->escapestring($phone)."', '".$db->escapestring($email)."', '".$sessionIdEsc."', '".$db->escapestring(substr($ipAddress, 0, 45))."', NOW())");

		$_SESSION['job_support_request_saved'] = true;
		$result['status'] = 200;
		$result['message'] = 'Thông tin của bạn đã được ghi nhận. Chúng tôi sẽ hỗ trợ bạn sớm nhất.';
		echo json_encode($result);
	}
	public function admin_verify_2fa()
	{
		$result = array();
		$pending = isset($_SESSION['admin_login_2fa']) && is_array($_SESSION['admin_login_2fa']) ? $_SESSION['admin_login_2fa'] : array();
		$code = trim(isset($_POST['code']) ? $_POST['code'] : '');

		if(empty($pending)){
			$result['status'] = 500;
			$result['code_expired'] = true;
			$result['expires_in'] = 0;
			$result['message'] = 'Phiên xác thực đã hết hạn. Vui lòng đăng nhập lại.';
			echo json_encode($result);
			return;
		}

		if($code === '' || !preg_match('/^\d{5}$/', $code)){
			$result['status'] = 500;
			$result['message'] = 'Vui lòng nhập đúng mã xác thực gồm 5 chữ số.';
			echo json_encode($result);
			return;
		}

		if(!isset($pending['expires_at']) || time() > intval($pending['expires_at'])){
			$this->adminClearTwoFactorSession();
			$result['status'] = 500;
			$result['code_expired'] = true;
			$result['expires_in'] = 0;
			$result['message'] = 'Mã xác thực đã hết hạn. Vui lòng đăng nhập lại để nhận mã mới.';
			echo json_encode($result);
			return;
		}

		if(!isset($pending['code_hash']) || md5($code) !== $pending['code_hash']){
			$result['status'] = 500;
			$result['message'] = 'Mã xác thực không chính xác.';
			echo json_encode($result);
			return;
		}

		$user = $this->adminFetchActiveUserById(isset($pending['user_id']) ? $pending['user_id'] : 0);
		if(!$user || intval($user->user_group) !== 1){
			$this->adminClearTwoFactorSession();
			$result['status'] = 500;
			$result['message'] = 'Không tìm thấy tài khoản xác thực hợp lệ.';
			echo json_encode($result);
			return;
		}

		$this->adminSetLoggedInSession($user);
		$this->adminClearTwoFactorSession();

		$result['status'] = 200;
		$result['message'] = 'Xác thực thành công';
		$result['return_url'] = XC_URL.'/admin';
		echo json_encode($result);
	}
	public function admin_resend_2fa()
	{
		$result = array();
		$pending = isset($_SESSION['admin_login_2fa']) && is_array($_SESSION['admin_login_2fa']) ? $_SESSION['admin_login_2fa'] : array();
		if(empty($pending) || !isset($pending['user_id'])){
			$result['status'] = 500;
			$result['message'] = 'Phiên xác thực đã hết hạn. Vui lòng đăng nhập lại.';
			echo json_encode($result);
			return;
		}

		$user = $this->adminFetchActiveUserById($pending['user_id']);
		if(!$user || intval($user->user_group) !== 1){
			$this->adminClearTwoFactorSession();
			$result['status'] = 500;
			$result['message'] = 'Không tìm thấy tài khoản xác thực hợp lệ.';
			echo json_encode($result);
			return;
		}

		$otpResult = $this->adminStartTwoFactorSession($user);
		if(!$otpResult['status']){
			$result['status'] = 500;
			$result['message'] = $otpResult['message'];
			echo json_encode($result);
			return;
		}

		$result['status'] = 200;
		$result['message'] = 'Mã xác thực mới đã được gửi về email của bạn.';
		$result['expires_in'] = $this->adminGetTwoFactorRemainingSeconds();
		echo json_encode($result);
	}
	
	
	public function stafflogin()
	{
		$result = array();
		$email = mysql_real_escape_string($_POST["username"]);
        $password = md5(mysql_real_escape_string($_POST["password"]));
		global $db;
		$db->query("SELECT * FROM hicrm_users WHERE user_email = '".$email."' AND user_password = '".$password."' AND user_group IN (1,2,3) ORDER BY id DESC LIMIT 1");
		if($db->num_row())
		{
			$user = $db->fetch_object(true);
			$_SESSION['staff']['id'] = $user->id;
			$_SESSION['staff']['fullname'] = $user->user_fullname;
			$_SESSION['staff']['group'] = $user->user_group;
			$_SESSION['staff']['department'] = $user->user_dept;
			$result["status"] = 200;
		}
		else
		{
			$result["status"] = 404;
			$result["message"] = "Không tìm thấy tài khoản";
		}
        echo json_encode($result);
	}
	public function login1()
	{
		$result = array();
		$email = mysql_real_escape_string($_POST["email"]);
        $password = md5(mysql_real_escape_string($_POST["password"]));
        $member_login = $this->model->get('memberloginModel')->user_login($email,$password);
		if($member_login)
		{
			$result["status"] = "200";
			$result["name"] = $_SESSION['user']['fullname'];
			//echo $_SESSION['user']['id'];
		}
		else
		{
			$result["status"] = "500";
			//echo "error";
		}
		echo json_encode($result);
	}
	public function addnote()
	{
		$result = array();
		global $db;
		$oid = $_POST["id"];
		$db->query("INSERT INTO ow_order_notes(oid,uid,note_text) VALUES('".$oid."','".$_SESSION['staff']['id']."','".$_POST['content']."')");
		$result["status"] = 200;
		echo json_encode($result);
	}
	public function CreatePackage()
	{
		$result = array();
		global $db;
		$packtype = $_POST['packtype'];
		$code = $this->func->generateid("package");
		$db->query("INSERT INTO ow_packages(pack_code,pack_created_by,pack_status,pack_type) VALUES('".$code."','".$_SESSION['staff']['id']."',1,'".$packtype."')");
		$result["status"] = 200;
		echo json_encode($result);
	}
	public function AssignCustomerToStaff()
	{
		$result = array();
		global $db;
		$cid = $_POST['cid'];
		$staff = $_POST['staff'];
		$note = $_POST['note'];
		//$result["data"] = $_POST;
		$db->query("SELECT * FROM hicrm_users WHERE id = '".$_SESSION['staff']['id']."'");
		if($db->num_row())
		{
			$user = $db->fetch_object(true);
			if($user->user_group == 1)
			{
				$db->query("SELECT * FROM ow_users WHERE id = '".$cid."'");
				if($db->num_row())
				{
					$db->query("UPDATE ow_users SET user_staff = '".$staff."' WHERE id = '".$cid."' LIMIT 1");
					$db->query("UPDATE ow_orders SET order_staff = '".$staff."' WHERE uid = '".$cid."'");
					$db->query("SELECT * FROM hicrm_users WHERE id = '".$staff."'");
					$staff = $db->fetch_object(true);
					$db->query("INSERT INTO ow_system_logs(uid,cid,log_key,log_value,log_note) VALUES('".$_SESSION['staff']['id']."','".$cid."','ASSIGN_CUSTOMER_TO_NEW_STAFF','".$staff."','".$note."')");
					$result["status"] = 200;
				}
				else
				{
					$result["status"] = 404;
					$result["message"] = "Không tìm thấy khách hàng này!";
				}
			}
			else
			{
				$result["status"] = 500;
			$result["message"] = "Bạn không đủ quyền để thực hiện tác vụ này!";
			}
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Bạn không đủ quyền để thực hiện tác vụ này!";
		}
		echo json_encode($result);
	}
	public function UpdateOrderStatus()
	{
		$result = array();
		global $db;
		$oid = $_POST["id"];
		$newstatus = $_POST['newstatus'];
		$updatename = $_POST["newstatustext"];
		$note = $_POST['note'];
		$db->query("UPDATE ow_orders SET order_status = '".$newstatus."' WHERE id = '".$oid."'");
		$noteupdate = "Cập nhật trang thái đơn hàng mới: ".$updatename;
		$db->query("SELECT * FROM ow_order_status WHERE id = '".$newstatus."'");
		$status = $db->fetch_object(true);
		$db->query("INSERT ow_order_updates(oid,uid,update_type,update_key,update_value) VALUES('".$oid."','".$_SESSION['staff']['id']."','2','".$status->status_key."','".$noteupdate."')");
		if($note != "")
		{
			$db->query("INSERT INTO ow_order_notes(oid,uid,note_text) VALUES('".$oid."','".$_SESSION['staff']['id']."','".$note."')");
		}
		$result["status"] = 200;
		echo json_encode($result);
	}
	public function GetItemsofOrder()
	{
		$result = array();
		$oid = $_POST["id"];
		global $db;
		$db->query("SELECT * FROM ow_order_items WHERE oid = '".$oid."'");
		$items = $db->fetch_object();
		$data = '';
		foreach($items as $item)
		{
			$disabled = ($item->item_buy == 1)? "disabled" : "";
			$data .= '<div class="col-md-12">
                            <div class="custom-control custom-block custom-control-primary">
                                <input type="checkbox" '.$disabled.' data-id="'.$item->id.'" class="custom-control-input" id="item-'.$item->id.'" name="buyitem[]">
                                <label class="custom-control-label" for="item-'.$item->id.'">
                                    <span class="d-flex align-items-center">
                                        <img class="img-avatar img-avatar48" src="'.$item->item_p_image.'" alt="">
                                        <span class="ml-2">
                                            <span class="font-w700">'.$item->item_pname.'</span>
                                            <span class="d-block font-size-sm text-muted">Price: '.number_format($item->item_price,0).' NDT | Size: '.$item->item_p_size.' | Color: '.$item->item_p_color.'</span> 
                                        </span>
                                    </span>
                                </label>
                                <span class="custom-block-indicator">
                                    <i class="fa fa-check"></i>
                                </span>
                            </div>
                        </div>';
		}
		$result["status"] = 200;
		$result["data"] = $data;
		echo json_encode($result);
	}
	public function ChangeItemMetas()
	{
		$result = array();
		global $db;
		$type = $_POST['type'];
		$id = $_POST['id'];
		$value = $_POST['values'];
		$db->query("UPDATE ow_order_items SET ".$type." = '".$value."' WHERE id = '".$id."'");
		$result["status"] = 200;
		echo json_encode($result);
	}
	public function UpdateOrderItemBuy()
	{
		$result = array();
		global $db;
		$oid = $_POST['id'];
		$items = $_POST['itemlist'];
		if(count($items) > 0)
		{
			$code = $_POST['note'];
			$items = implode(",",$items);
			$db->query("SELECT * FROM ow_order_items WHERE id IN (".$items.")");
			$listitems = $db->fetch_object();
			foreach($listitems as $item)
			{
				$db->query("UPDATE ow_order_items SET item_buy = '1',item_status = 2, item_kr_tracking_code = '".$code."', item_buy_date = '".date("Y-m-d H:i:s")."' WHERE id = '".$item->id."' AND item_buy = 0");
				$note = "Mua sản phẩm: ".$item->item_pname." (Vận đơn: ".$code.")";
				$db->query("INSERT ow_order_updates(oid,uid,update_type,update_value) VALUES('".$oid."','".$_SESSION['staff']['id']."','2','".$note."')");
				$db->query("INSERT INTO ow_order_notes(oid,uid,note_text) VALUES('".$oid."','".$_SESSION['staff']['id']."','".$note."')");
			}
			
			$result["status"] = 200;
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Không có dữ liệu cập nhật!";
		}
		echo json_encode($result);
	}
	public function ScanTrackingItemIntoKRStock()
	{
		$result = array();
		global $db;
		$code = $_POST['code'];
		$db->query("SELECT *, oi.id as oiid,o.id as oid FROM ow_order_items as oi
		LEFT JOIN ow_orders as o ON oi.oid = o.id
		WHERE item_status = 2 AND item_kr_tracking_code = '".$code."'");
		if($db->num_row())
		{
			$item = $db->fetch_object(true);
			$data = '<tr>
				<td class="text-center">
					<img class="img-avatar img-avatar48" src="'.$item->item_p_image.'" alt="">
				</td>
				<td class="font-w600">
					<a href="javascript:void(0)" class="font-size-sm">'.$item->item_pname.'</a>
				</td>
				<td class="d-none d-sm-table-cell">
					'.$item->order_code.'
				</td>
				<td class="d-none d-lg-table-cell">
					<div class="form-group mb-0">
						<div class="input-group">
							<input type="text" class="form-control item-meta-weight" value="'.$item->item_weight.'" data-id="'.$item->oiid.'" id="item-meta-weight" name="item-meta-width">
						</div>
					</div>
				</td>
				<td class="text-center">
					<div class="form-group mb-0 form-row">
				<div class="col-12">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text">
								L
							</span>
						</div>
						<input type="text" value="'.$item->item_lenght.'" class="form-control item-meta-lenght" data-id="'.$item->oiid.'" id="item-meta-lenght" name="item-meta-lenght">
						<div class="input-group-prepend">
							<span class="input-group-text">
								W
							</span>
						</div>
						<input type="text" class="form-control item-meta-width" value="'.$item->item_width.'" data-id="'.$item->oiid.'" id="item-meta-width" name="item-meta-width">
						<div class="input-group-prepend">
							<span class="input-group-text">
								H
							</span>
						</div>
						<input type="text" class="form-control item-meta-height" value="'.$item->item_height.'" data-id="'.$item->oiid.'" id="item-meta-height" name="item-meta-height">
					</div>
				</div>
				
			</div>
				</td>
				<td><button type="button" class="btn btn-sm btn-secondary js-tooltip-enabled" data-toggle="tooltip" title="" data-original-title="Edit">
                                        <i class="fa fa-check-square"></i>
                                    </button></td>
			</tr>';
			$db->query("UPDATE ow_order_items SET item_status = 3 WHERE id = '".$item->oiid."'");
			$note = "Sản phẩm: ".$item->item_pname." (Vận đơn: ".$code.") đã về kho Hàn Quốc";
			$db->query("INSERT ow_order_updates(oid,uid,update_type,update_value) VALUES('".$item->oid."','".$_SESSION['staff']['id']."','2','".$note."')");
			$result["status"] = 200;
			$result["data"] = $data;
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Không tìm thấy sản phẩm có vận đơn phù hợp!";
		}
		
		
			
			echo json_encode($result);
		
	}
	public function ScanItemForPackage()
	{
		$result = array();
		global $db;
		$code = $_POST['code'];
		$pid = $_POST['id'];
		$db->query("SELECT *, oi.id as oiid FROM ow_order_items as oi
		LEFT JOIN ow_orders as o ON oi.oid = o.id
		WHERE item_status = 2 AND item_kr_tracking_code = '".$code."' AND (SELECT count(*) FROM ow_package_items WHERE itemid = oi.id) = 0");
		if($db->num_row())
		{
			$item = $db->fetch_object(true);
			$data = '<tr>
						<td class="text-center">
							<img class="img-avatar img-avatar48" src="'.$item->item_p_image.'" alt="">
						</td>
						<td class="font-w600">
							<a href="javascript:void(0)" class="font-size-sm">'.$item->item_pname.'</a>
						</td>
						<td class="d-none d-sm-table-cell">
							'.$item->order_code.'
						</td>
						<td class="d-none d-lg-table-cell text-center">
							'.number_format($item->item_weight,0).'
						</td>
						<td class="text-center">
							<div class="form-group mb-0 form-row">
						<div class="col-12">
							'.number_format($item->item_lenght,0).' x '.number_format($item->item_width,0).' x '.number_format($item->item_height,0).'
						</td>
						<td><button type="button" class="btn btn-sm btn-danger js-tooltip-enabled" data-toggle="tooltip" title="" data-original-title="Delete">
												<i class="fa fa-times"></i>
											</button></td>
					</tr>';
			$db->query("INSERT INTO ow_package_items(pid,itemid) VALUES('".$pid."','".$item->oiid."')");
			$result["status"] = 200;
			$result["data"] = $data;
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Không tìm thấy sản phẩm có vận đơn phù hợp!";
		}
		
		
			
			echo json_encode($result);
		
	}
	public function UpdateOrderFixFee()
	{
		$result = array();
		global $db;
		$oid = $_POST['id'];
		$type = $_POST['type'];
		$amount = $_POST['amount'];
		$typename = $_POST['typename'];
		$db->query("SELECT * FROM ow_orders WHERE id = '".$oid."' LIMIT 1");
		$order = $db->fetch_object(true);
		
		$db->query("SELECT * FROM ow_users WHERE id = '".$order->uid."'");
		$user = $db->fetch_object(true);
		if($user->user_available_wallet <= $amount)
		{
			$result["status"] = 500;
			$result["message"] = "Số dư của Khách hàng không đủ để thực hiện giao dịch này!";
		}
		else
		{
			$db->query("UPDATE ow_orders SET ".$type." = '".$amount."' WHERE id = '".$oid."'");
			$note = "Cập nhật ".$typename." số tiền: ".number_format($amount,0)."đ";
			
			$db->query("INSERT ow_order_updates(oid,uid,update_type,update_value) VALUES('".$oid."','".$_SESSION['staff']['id']."','4','".$note."')");
			$db->query("UPDATE ow_users SET user_available_wallet = user_available_wallet - ".$amount." WHERE id = '".$order->uid."'");
			$transcode = general::getInstance()->generateid("transaction");
			
			$sql = "INSERT INTO ow_transactions(uid,trans_code,trans_type,trans_bank,trans_method,trans_amount,trans_hash,trans_status,trans_note) VALUES('".$order->uid."','".$transcode."','2','1','1','".$amount."','".bin2hex(mcrypt_create_iv(16, MCRYPT_DEV_URANDOM))."','2','Thanh toán phí cho đơn hàng".$order->order_code."')";
			$db->query($sql);
			$db->query("INSERT INTO ow_order_notes(oid,uid,note_text) VALUES('".$oid."','".$_SESSION['staff']['id']."','".$note."')");
			$result["status"] = 200;
		}
		
		echo json_encode($result);
	}
	public function AssignToStaff()
	{
		$result = array();
		global $db;
		$oid = $_POST['id'];
		$staffname = $_POST['staffname'];
		$db->query("UPDATE ow_orders SET order_staff = '".$_POST['staff']."', order_assign_date = '".date("Y-m-d H:i:s")."' WHERE id = '".$oid."'");
		$note = "Giao đơn hàng cho ".$staffname;
		$db->query("INSERT ow_order_updates(oid,uid,update_type,update_value) VALUES('".$oid."','".$_SESSION['staff']['id']."','4','".$note."')");
		$db->query("INSERT INTO ow_order_notes(oid,uid,note_text) VALUES('".$oid."','".$_SESSION['staff']['id']."','".$_POST['note']."')");
		$result["status"] = 200;
		echo json_encode($result);
	}
	public function approvetransaction()
	{
		$result = array();
		$tid = $_POST['id'];
		global $db;
		$db->query("SELECT * FROM ow_transactions WHERE id = '".$tid."' AND trans_status = 1");
		if($db->num_row())
		{
			$trans = $db->fetch_object(true);
			switch($trans->trans_type)
			{
				case 1:
				{
					$result["uid"] = $trans->uid;
					$result["sql"] = $sql = "UPDATE ow_users SET user_available_wallet = user_available_wallet + ".$trans->trans_amount." WHERE id = '".$trans->uid."'";
					$db->query($sql);
					$db->query("UPDATE ow_transactions SET trans_status = 2, trans_approved_by = '".$_SESSION['staff']['id']."', trans_approved_date = '".date("Y-m-d H:i:s")."' WHERE id = '".$tid."'");
					$result["status"] = 200;
					break;
				}
				default:
				{
					$result["status"] = 500;
					$result["message"] = "Không rõ yêu cầu!";
					break;
				}
			}
			
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Không tồn tại giao dịch này hoặc đã được xử lý!";
		}
		echo json_encode($result);
	}
	public function deninetransaction()
	{
		$result = array();
		$tid = $_POST['id'];
		global $db;
		$db->query("SELECT * FROM ow_transactions WHERE id = '".$tid."' AND trans_status = 1");
		if($db->num_row())
		{
			$trans = $db->fetch_object(true);
			switch($trans->trans_type)
			{
				case 1:
				{
					$db->query("UPDATE ow_transactions SET trans_status = 3, trans_approved_by = '".$_SESSION['staff']['id']."', trans_approved_date = '".date("Y-m-d H:i:s")."' WHERE id = '".$tid."'");
					$result["status"] = 200;
					break;
				}
				default:
				{
					$result["status"] = 500;
					$result["message"] = "Không rõ yêu cầu!";
					break;
				}
			}
			
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Không tồn tại giao dịch này hoặc đã được xử lý!";
		}
		echo json_encode($result);
	}
	public function getuser()
	{
		global $db;
		$result = array();
		if(isset($_POST['id']) && $_POST['id'] != "")
		{
			$db->query("SELECT *, u.id as uid FROM hicrm_users as u 
			LEFT JOIN hicrm_departments as d ON u.user_dept = d.id
			WHERE u.id = '".$_POST['id']."'");
			if($db->num_row())
			{
				$user = $db->fetch_object(true);
				$result["id"] = $user->uid;
				$result["email"] = $user->user_email;
				$result["name"] = $user->user_fullname;
				$result["group"] = $user->user_group;
				$result["status"] = 200;
			}
			else
			{
				$result["status"] = 500;
				$result["message"] = "Tài khoản không tồn tại!";
			}
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Dữ liệu không hợp lệ!";
		}
		echo json_encode($result);
	}
	// public function adduser()
	// {
	// 	global $db;
	// 	$result = array();
	// 	if(isset($_POST['updatetype']) && $_POST['updatetype'] == "new")
	// 	{
	// 		$email = mysql_real_escape_string($_POST["email"]);
	// 		$password = md5(mysql_real_escape_string($_POST["password"]));
	// 		$name = $_POST['name'];
	// 		$group = $_POST['group'];
	// 		$dept  = $_POST['dept'];
	// 		if(filter_var($email, FILTER_VALIDATE_EMAIL))
	// 		{
	// 			$db->query("SELECT * FROM hicrm_users WHERE user_email = '".$email."'");
	// 			if($db->num_row())
	// 			{
	// 				$result["status"] = 500;
	// 				$result["message"] = "Địa chỉ email đã được sử dụng!";
	// 			}
	// 			else
	// 			{
	// 				$db->query("INSERT INTO hicrm_users(user_email,user_password,user_fullname,user_group,user_dept,user_status) VALUES('".$email."','".$password."','".$name."','".$group."','".$dept."',1)");
	// 				$result["status"] = 200;
	// 			}
	// 		}
	// 		else
	// 		{
	// 			$result["status"] = 500;
	// 			$result["message"] = "Địa chỉ email không hợp lệ!";
	// 		}
	// 	}
	// 	elseif(isset($_POST['updatetype']) && $_POST['updatetype'] == "edit")
	// 	{
	// 		$password = "";
	// 		if($_POST["password"] != "")
	// 		{
	// 			$password = md5(mysql_real_escape_string($_POST["password"]));
	// 		}
	// 		$name = $_POST['name'];
	// 		$group = $_POST['group'];
	// 		$uid = $_POST['uid'];
	// 		$dept = $_POST['dept'];
	// 		if($password != "")
	// 		{
	// 			$db->query("UPDATE hicrm_users SET user_fullname = '".$name."', user_group = '".$group."',user_dept = '".$dept."', user_password = '".$password."' WHERE id = '".$uid."'");
	// 			$result["status"] = 200;
	// 		}
	// 		else
	// 		{
	// 			$db->query("UPDATE hicrm_users SET user_fullname = '".$name."', user_group = '".$group."',user_dept = '".$dept."' WHERE id = '".$uid."'");
	// 			$result["status"] = 200;
	// 		}
	// 	}
		
	// 	echo json_encode($result);
	// }
	public function deleteuser()
	{
		global $db;
		if(!$this->requireAdminApiPermission('users')){ return; }
		$result = array();
		$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
		$db->query("SELECT * FROM hicrm_users WHERE id = '".$id."'");
		if($db->num_row())
		{
			$user = $db->fetch_object(true);
			if($id === intval($_SESSION['user']['id']) || (intval($user->user_group) === 1 && !$this->currentAdminIsSuperAdmin())){
				$result["status"] = 403;
				$result["message"] = "Không thể xóa tài khoản đang đăng nhập hoặc tài khoản Super Admin.";
				echo json_encode($result);
				return;
			}
			$db->query("UPDATE hicrm_users SET user_status = '99' WHERE id = '".$id."'");
			$result["status"] = 200;
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Tài khoản không tồn tại";
		}
		echo json_encode($result);
	}
	
	public function deletelisting()
	{
		$result = array();
		global $db;
		$id = $_POST['id'];
		$db->query("SELECT * FROM bds_posts WHERE post_author = '".$_SESSION['user']['id']."' AND id = '".$id."'");
		if($db->num_row())
		{
			$db->query("DELETE FROM bds_posts WHERE post_author = '".$_SESSION['user']['id']."' AND id = '".$id."'");
			$result["status"] = 200;
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Bạn không đủ quyền thực hiện thao tác này!";
		}
		echo json_encode($result);
	}
	public function admindeletelisting()
	{
		$result = array();
		global $db;
		$id = $_POST['id'];
		$db->query("SELECT * FROM hicrm_users WHERE id = '".$_SESSION['staff']['id']."' AND user_group IN (1,2,3)");
		if($db->num_row())
		{
			$db->query("SELECT * FROM bds_posts WHERE id = '".$id."'");
			if($db->num_row())
			{
				$db->query("UPDATE bds_posts SET post_status = 3 WHERE id = '".$id."'");
				$db->query("INSERT INTO bds_logs(uid,log_key,log_value) VALUES('".$_SESSION['staff']['id']."','DELETE_LISTING','".$id."')");
				$result["status"] = 200;
			}
			else
			{
				$result["status"] = 500;
				$result["message"] = "Không tồn tại nội dung này!";
			}
			
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Bạn không có quyền thực hiện thao tác này!";
		}
		echo json_encode($result);
	}
	public function adminapprovedlisting()
	{
		$result = array();
		global $db;
		$id = $_POST['id'];
		$db->query("SELECT * FROM hicrm_users WHERE id = '".$_SESSION['staff']['id']."' AND user_group IN (1,2,3)");
		if($db->num_row())
		{
			$db->query("SELECT * FROM bds_posts WHERE id = '".$id."'");
			if($db->num_row())
			{
				$db->query("UPDATE bds_posts SET post_status = 1 WHERE id = '".$id."'");
				$db->query("INSERT INTO bds_logs(uid,log_key,log_value) VALUES('".$_SESSION['staff']['id']."','APPROVED_LISTING','".$id."')");
				$result["status"] = 200;
			}
			else
			{
				$result["status"] = 500;
				$result["message"] = "Không tồn tại nội dung này!";
			}
			
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Bạn không có quyền thực hiện thao tác này!";
		}
		echo json_encode($result);
	}
	public function addproject()
	{
		global $db;
		$result = array();
		$updatetype = $_POST['updatetype'];
		if(isset($_POST['updatetype']) && $_POST['updatetype'] == "new")
		{
			$FinalFilenameFront = "";
			if(isset($_FILES['anhbia']))
			{
				$errors= array();
				$file_name = $_FILES['anhbia']['name'];
				$file_size =$_FILES['anhbia']['size'];
				$file_tmp =$_FILES['anhbia']['tmp_name'];
				$file_type=$_FILES['anhbia']['type'];
				$file_ext=strtolower(end(explode('.',$_FILES['anhbia']['name'])));
				$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['anhbia']['name']); 
				$FinalFilenameFront = md5(time())."-".$FinalFilename;
				if(in_array($file_ext,$expensions)=== false){
					$errors[]="Extension not allowed, please choose a .png, .jpg file.";
				}
				if($file_size > 5242880){
					$errors[]='File size must be max 2Mb';
				}
				if(empty($errors)==true){
					move_uploaded_file($file_tmp,"./uploads/post/".$FinalFilenameFront);
					
				}else
				{
					$result["status"] = 500;
				}
			}
			$db->query("INSERT INTO bds_projects(project_name,project_holder,project_address,project_categories,project_area,project_scale,project_area_detail,project_price,project_intro) VALUES('".$_POST['name']."','".$_POST['holder']."','".$_POST['address']."','".$_POST['category']."','".$_POST['area']."','".$_POST['scale']."','".$_POST['ad']."','".$_POST['price']."','".$_POST['noidung']."')");
			$db->query("SELECT id FROM bds_projects ORDER BY id DESC LIMIT 1");
			$pid = $db->fetch_object(true)->id;
			$db->query("INSERT INTO bds_project_images(pid,image_url,image_feature) VALUES('".$pid."','".$FinalFilenameFront."',1)");
			if(isset($_POST['hinhanh']) && count($_POST['hinhanh']))
			{
				$hinhanh = $_POST['hinhanh'];
				$i = 0;
				foreach($hinhanh as $img)
				{
					$f = ($i == 0)? 1 : 0;
					$db->query("INSERT INTO bds_project_images(pid,image_url,image_feature) VALUES('".$pid."','".$img."','0')");
					$i++;
				}
			}
			$result["status"] = 200;
			$result["url"] = XC_URL."/uploads/general/".$FinalFilenameFront;
			$result["id"] = $FinalFilenameFront;
		}
		elseif(isset($_POST['updatetype']) && $_POST['updatetype'] == "edit")
		{
			$db->query("SELECT * FROM bds_projects WHERE id = '".$_POST['id']."'");
			if($db->num_row())
			{
				$FinalFilenameFront = "";
				$FinalFilenameFront2 = "";
				//echo $_FILES['hinhanh']['name']."ssss";
				if(isset($_FILES['hinhanh']))
				{
					$errors= array();
					$file_name = $_FILES['hinhanh']['name'];
					$file_size =$_FILES['hinhanh']['size'];
					$file_tmp =$_FILES['hinhanh']['tmp_name'];
					$file_type=$_FILES['hinhanh']['type'];
					$file_ext=strtolower(end(explode('.',$_FILES['hinhanh']['name'])));
					$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['hinhanh']['name']); 
					$FinalFilenameFront = md5(time())."-".$FinalFilename;
					if(in_array($file_ext,$expensions)=== false){
						$errors[]="Extension not allowed, please choose a .png, .jpg file.";
					}
					if($file_size > 5242880){
						$errors[]='File size must be max 2Mb';
					}
					if(empty($errors)==true){
						move_uploaded_file($file_tmp,"./uploads/general/".$FinalFilenameFront);
						
					}else
					{
						$result["status"] = 500;
					}
					
				}
				if(isset($_FILES['feature']))
				{
					$errors= array();
					$file_name = $_FILES['feature']['name'];
					$file_size =$_FILES['feature']['size'];
					$file_tmp =$_FILES['feature']['tmp_name'];
					$file_type=$_FILES['feature']['type'];
					$file_ext=strtolower(end(explode('.',$_FILES['feature']['name'])));
					$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['feature']['name']); 
					$FinalFilenameFront2 = md5(time())."-".$FinalFilename;
					if(in_array($file_ext,$expensions)=== false){
						$errors[]="Extension not allowed, please choose a .png, .jpg file.";
					}
					if($file_size > 5242880){
						$errors[]='File size must be max 2Mb';
					}
					if(empty($errors)==true){
						move_uploaded_file($file_tmp,"./uploads/general/".$FinalFilenameFront2);
						
					}else
					{
						$result["status"] = 500;
					}
					
				}
				$updateimage = ($FinalFilenameFront != "")? ", place_image = '".$FinalFilenameFront."'" : "";
				$updateimage2 = ($FinalFilenameFront2 != "")? ", place_feature_image = '".$FinalFilenameFront2."'" : "";
				$db->query("UPDATE bds_projects SET project_name = '".$_POST['name']."', project_address = '".$_POST['address']."', project_categories = '".$_POST['category']."', project_area = '".$_POST['area']."',project_scale = '".$_POST['scale']."',project_area_detail = '".$_POST['ad']."',project_price = '".$_POST['price']."',project_intro = '".$_POST['noidung']."' WHERE id = '".$_POST['id']."'");
				if($FinalFilenameFront != "")
				{
					$db->query("DELETE FROM bds_project_images WHERE pid = '".$_POST['id']."' AND image_feature = 1");
					$db->query("INSERT INTO bds_project_images(pid,image_url,image_feature) VALUES('".$pid."','".$FinalFilenameFront."',1)");
				}
				if(isset($_POST['hinhanh']) && count($_POST['hinhanh']))
				{
					$db->query("DELETE FROM bds_project_images WHERE pid = '".$_POST['id']."' AND image_feature = 0");
					$hinhanh = $_POST['hinhanh'];
					$i = 0;
					foreach($hinhanh as $img)
					{
						$f = ($i == 0)? 1 : 0;
						$db->query("INSERT INTO bds_project_images(pid,image_url,image_feature) VALUES('".$pid."','".$img."','0')");
						$i++;
					}
				}
				$result["status"] = 200;
			}
			else
			{
				$result["status"] = 500;
				$result["message"] = "Không tồn tại nội dung này!";
			}
		}
		echo json_encode($result);
		
	}
	public function addplace()
	{
		global $db;
		$result = array();
		$updatetype = $_POST['updatetype'];
		if(isset($_POST['updatetype']) && $_POST['updatetype'] == "new")
		{
			$FinalFilenameFront = "";
			$FinalFilenameFront2 = "";
			//echo $_FILES['hinhanh']['name']."ssss";
			if(isset($_FILES['hinhanh']))
			{
				$errors= array();
				$file_name = $_FILES['hinhanh']['name'];
				$file_size =$_FILES['hinhanh']['size'];
				$file_tmp =$_FILES['hinhanh']['tmp_name'];
				$file_type=$_FILES['hinhanh']['type'];
				$file_ext=strtolower(end(explode('.',$_FILES['hinhanh']['name'])));
				$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['hinhanh']['name']); 
				$FinalFilenameFront = md5(time())."-".$FinalFilename;
				if(in_array($file_ext,$expensions)=== false){
					$errors[]="Extension not allowed, please choose a .png, .jpg file.";
				}
				if($file_size > 5242880){
					$errors[]='File size must be max 2Mb';
				}
				if(empty($errors)==true){
					move_uploaded_file($file_tmp,"./uploads/general/".$FinalFilenameFront);
					
				}else
				{
					$result["status"] = 500;
				}
				
			}
			if(isset($_FILES['feature']))
			{
				$errors= array();
				$file_name = $_FILES['feature']['name'];
				$file_size =$_FILES['feature']['size'];
				$file_tmp =$_FILES['feature']['tmp_name'];
				$file_type=$_FILES['feature']['type'];
				$file_ext=strtolower(end(explode('.',$_FILES['feature']['name'])));
				$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['feature']['name']); 
				$FinalFilenameFront2 = md5(time())."-".$FinalFilename;
				if(in_array($file_ext,$expensions)=== false){
					$errors[]="Extension not allowed, please choose a .png, .jpg file.";
				}
				if($file_size > 5242880){
					$errors[]='File size must be max 2Mb';
				}
				if(empty($errors)==true){
					move_uploaded_file($file_tmp,"./uploads/general/".$FinalFilenameFront2);
					
				}else
				{
					$result["status"] = 500;
				}
				
			}
			$db->query("INSERT INTO bds_places(place_name,place_district,place_province,place_about,place_image,place_feature_image) VALUES('".$_POST['title']."','".$_POST['huyen']."','".$_POST['tinh']."','".$_POST['noidung']."','".$FinalFilenameFront."','".$FinalFilenameFront2."')");
			$result["status"] = 200;
			$result["url"] = XC_URL."/uploads/general/".$FinalFilenameFront;
			$result["id"] = $FinalFilenameFront;
		}
		elseif(isset($_POST['updatetype']) && $_POST['updatetype'] == "edit")
		{
			$db->query("SELECT * FROM bds_places WHERE id = '".$_POST['id']."'");
			if($db->num_row())
			{
				$FinalFilenameFront = "";
				$FinalFilenameFront2 = "";
				//echo $_FILES['hinhanh']['name']."ssss";
				if(isset($_FILES['hinhanh']))
				{
					$errors= array();
					$file_name = $_FILES['hinhanh']['name'];
					$file_size =$_FILES['hinhanh']['size'];
					$file_tmp =$_FILES['hinhanh']['tmp_name'];
					$file_type=$_FILES['hinhanh']['type'];
					$file_ext=strtolower(end(explode('.',$_FILES['hinhanh']['name'])));
					$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['hinhanh']['name']); 
					$FinalFilenameFront = md5(time())."-".$FinalFilename;
					if(in_array($file_ext,$expensions)=== false){
						$errors[]="Extension not allowed, please choose a .png, .jpg file.";
					}
					if($file_size > 5242880){
						$errors[]='File size must be max 2Mb';
					}
					if(empty($errors)==true){
						move_uploaded_file($file_tmp,"./uploads/general/".$FinalFilenameFront);
						
					}else
					{
						$result["status"] = 500;
					}
					
				}
				if(isset($_FILES['feature']))
				{
					$errors= array();
					$file_name = $_FILES['feature']['name'];
					$file_size =$_FILES['feature']['size'];
					$file_tmp =$_FILES['feature']['tmp_name'];
					$file_type=$_FILES['feature']['type'];
					$file_ext=strtolower(end(explode('.',$_FILES['feature']['name'])));
					$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['feature']['name']); 
					$FinalFilenameFront2 = md5(time())."-".$FinalFilename;
					if(in_array($file_ext,$expensions)=== false){
						$errors[]="Extension not allowed, please choose a .png, .jpg file.";
					}
					if($file_size > 5242880){
						$errors[]='File size must be max 2Mb';
					}
					if(empty($errors)==true){
						move_uploaded_file($file_tmp,"./uploads/general/".$FinalFilenameFront2);
						
					}else
					{
						$result["status"] = 500;
					}
					
				}
				$updateimage = ($FinalFilenameFront != "")? ", place_image = '".$FinalFilenameFront."'" : "";
				$updateimage2 = ($FinalFilenameFront2 != "")? ", place_feature_image = '".$FinalFilenameFront2."'" : "";
				$db->query("UPDATE bds_places SET place_name = '".$_POST['title']."', place_province = '".$_POST['tinh']."', place_district = '".$_POST['huyen']."', place_about = '".$_POST['noidung']."' ".$updateimage."".$updateimage2." WHERE id = '".$_POST['id']."'");
				$result["status"] = 200;
			}
			else
			{
				$result["status"] = 500;
				$result["message"] = "Không tồn tại nội dung này!";
			}
		}
		echo json_encode($result);
	}
	//===================================== NEWS FUNCTION ======================//
	
	public function addpage()
	{
		global $db;
		$result = array();
		$updatetype = $_POST['updatetype'];
		if(isset($_POST['updatetype']) && $_POST['updatetype'] == "new")
		{
			$FinalFilenameFront = "";
			
			if(isset($_FILES['hinhanh']))
			{
				$errors= array();
				$file_name = $_FILES['hinhanh']['name'];
				$file_size =$_FILES['hinhanh']['size'];
				$file_tmp =$_FILES['hinhanh']['tmp_name'];
				$file_type=$_FILES['hinhanh']['type'];
				$file_ext=strtolower(end(explode('.',$_FILES['hinhanh']['name'])));
				$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['hinhanh']['name']); 
				$FinalFilenameFront = md5(time())."-".$FinalFilename;
				if(in_array($file_ext,$expensions)=== false){
					$errors[]="Extension not allowed, please choose a .png, .jpg file.";
				}
				if($file_size > 5242880){
					$errors[]='File size must be max 2Mb';
				}
				if(empty($errors)==true){
					move_uploaded_file($file_tmp,"./uploads/general/".$FinalFilenameFront);
					
				}else
				{
					$result["status"] = 500;
				}
				
			}
			
			$db->query("INSERT INTO bds_pages(page_title,page_content,page_author,page_view,page_image) VALUES('".$_POST['title']."','".$_POST['noidung']."','".$_SESSION['staff']['id']."',0,'".$FinalFilenameFront."')");
			$result["status"] = 200;
			$result["url"] = XC_URL."/uploads/general/".$FinalFilenameFront;
			$result["id"] = $FinalFilenameFront;
		}
		elseif(isset($_POST['updatetype']) && $_POST['updatetype'] == "edit")
		{
			$db->query("SELECT * FROM bds_pages WHERE id = '".$_POST['id']."'");
			if($db->num_row())
			{
				$FinalFilenameFront = "";
				//echo $_FILES['hinhanh']['name']."ssss";
				if(isset($_FILES['hinhanh']))
				{
					$errors= array();
					$file_name = $_FILES['hinhanh']['name'];
					$file_size =$_FILES['hinhanh']['size'];
					$file_tmp =$_FILES['hinhanh']['tmp_name'];
					$file_type=$_FILES['hinhanh']['type'];
					$file_ext=strtolower(end(explode('.',$_FILES['hinhanh']['name'])));
					$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['hinhanh']['name']); 
					$FinalFilenameFront = md5(time())."-".$FinalFilename;
					if(in_array($file_ext,$expensions)=== false){
						$errors[]="Extension not allowed, please choose a .png, .jpg file.";
					}
					if($file_size > 5242880){
						$errors[]='File size must be max 2Mb';
					}
					if(empty($errors)==true){
						move_uploaded_file($file_tmp,"./uploads/general/".$FinalFilenameFront);
						
					}else
					{
						$result["status"] = 500;
					}
					
				}
				
				$updateimage = ($FinalFilenameFront != "")? ", page_image = '".$FinalFilenameFront."'" : "";
				$db->query("UPDATE bds_pages SET page_title = '".$_POST['title']."', page_content = '".$_POST['noidung']."' ".$updateimage." WHERE id = '".$_POST['id']."'");
				$result["status"] = 200;
			}
			else
			{
				$result["status"] = 500;
				$result["message"] = "Không tồn tại nội dung này!";
			}
		}
		echo json_encode($result);
	}
	public function deletepage()
	{
		global $db;
		$result = array();
		$db->query("SELECT * FROM bds_pages WHERE id = '".$_POST['id']."'");
		if($db->num_row())
		{
			$db->query("DELETE FROM bds_pages WHERE id = '".$_POST['id']."'");
			$result["status"] = 200;
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Nội dung không tồn tại";
		}
		echo json_encode($result);
	}
	public function getpage()
	{
		global $db;
		$result = array();
		$db->query("SELECT * FROM bds_pages WHERE id = '".$_POST['id']."' LIMIT 1");
		if($db->num_row())
		{
			$news = $db->fetch_object(true);
			$result["status"] = 200;
			$result["title"] = $news->page_title;
			$result["content"] = $news->page_content;
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Nội dung không tồn tại";
		}
		
		echo json_encode($result);
	}
	public function getnews()
	{
		global $db;
		$result = array();
		$db->query("SELECT * FROM bds_news WHERE id = '".$_POST['id']."' LIMIT 1");
		if($db->num_row())
		{
			$news = $db->fetch_object(true);
			$result["status"] = 200;
			$result["title"] = $news->news_title;
			$result["content"] = $news->news_content;
			$db->query("SELECT * FROM bds_news_categories");
			$cats = $db->fetch_object();
			$cate = '';
			foreach($cats as $d)
			{
				$selected = ($d->id == $news->news_category)? "selected" : "";
				$cate .= '<option value="'.$d->id.'" '.$selected.'>'.$d->cat_name.'</option>';
			}
			$result["category"] = $cate;
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Nội dung không tồn tại";
		}
		
		echo json_encode($result);
	}
	public function deletenews()
	{
		global $db;
		$result = array();
		$db->query("SELECT * FROM hicrm_news WHERE id = '".$_POST['id']."'");
		if($db->num_row())
		{
			$db->query("UPDATE hicrm_news SET new_status = '99' WHERE id = '".$_POST['id']."'");
			$result["status"] = 200;
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Nội dung không tồn tại";
		}
		echo json_encode($result);
	}
	//===================================== END NEWS FUNCTION ======================//
	
	
	//==================================CATEGORY FUNCTION ==========================//
	public function addcat()
	{
		global $db;
		$result = array();
		$updatetype = $_POST['updatetype'];
		$title = $_POST['title'];
		if(isset($_POST['updatetype']) && $_POST['updatetype'] == "new")
		{
			$db->query("SELECT * FROM bds_categories WHERE cat_name = '".$title."'");
			if($db->num_row())
			{
				$result["status"] = 400;
				$result["message"] = "Danh mục này đã tồn tại!";
			}
			else
			{
				$db->query("INSERT INTO bds_categories(cat_name) VALUES('".$title."')");
				$result["status"] = 200;
			}
		}
		else
		{
			$id = $_POST['id'];
			$db->query("SELECT * FROM bds_categories WHERE id = '".$id."'");
			if($db->num_row())
			{
				$db->query("SELECT * FROM bds_categories WHERE cat_name = '".$title."'");
				if($db->num_row())
				{
					$result["status"] = 400;
					$result["message"] = "Danh mục này đã tồn tại!";
				}
				else
				{
					$db->query("UPDATE bds_categories SET cat_name = '".$title."' WHERE id = '".$id."'");
					$result["status"] = 200;
				}
			}
			else
			{
				$result["status"] = 404;
				$result["message"] = "Không tìm thấy nội dung này";
			}
		}
		echo json_encode($result);
	}
	public function adddepart()
	{
		global $db;
		$result = array();
		$updatetype = $_POST['updatetype'];
		$title = $_POST['title'];
		if(isset($_POST['updatetype']) && $_POST['updatetype'] == "new")
		{
			$db->query("SELECT * FROM hicrm_departments WHERE depart_name = '".$title."'");
			if($db->num_row())
			{
				$result["status"] = 400;
				$result["message"] = "Phòng ban này đã tồn tại!";
			}
			else
			{
				$db->query("INSERT INTO hicrm_departments(depart_name) VALUES('".$title."')");
				$result["status"] = 200;
			}
		}
		else
		{
			$id = $_POST['id'];
			$db->query("SELECT * FROM hicrm_departments WHERE id = '".$id."'");
			if($db->num_row())
			{
				$db->query("SELECT * FROM hicrm_departments WHERE depart_name = '".$title."'");
				if($db->num_row())
				{
					$result["status"] = 400;
					$result["message"] = "Phòng ban này đã tồn tại!";
				}
				else
				{
					$db->query("UPDATE hicrm_departments SET depart_name = '".$title."' WHERE id = '".$id."'");
					$result["status"] = 200;
				}
			}
			else
			{
				$result["status"] = 404;
				$result["message"] = "Không tìm thấy phòng ban này";
			}
		}
		echo json_encode($result);
	}
	public function addmenu()
	{
		global $db;
		$result = array();
		$updatetype = $_POST['updatetype'];
		$title = $_POST['title'];
		$url = $_POST['url'];
		$order = $_POST['order'];
		if(isset($_POST['updatetype']) && $_POST['updatetype'] == "new")
		{
			$db->query("SELECT * FROM bds_menus WHERE menu_title = '".$title."'");
			if($db->num_row())
			{
				$result["status"] = 400;
				$result["message"] = "Danh mục này đã tồn tại!";
			}
			else
			{
				$db->query("INSERT INTO bds_menus(menu_title,menu_url,menu_order) VALUES('".$title."','".$url."','".$order."')");
				$result["status"] = 200;
			}
		}
		else
		{
			$id = $_POST['id'];
			$db->query("SELECT * FROM bds_menus WHERE id = '".$id."'");
			if($db->num_row())
			{
				$db->query("SELECT * FROM bds_menus WHERE menu_title = '".$title."'");
				if($db->num_row())
				{
					$result["status"] = 400;
					$result["message"] = "Danh mục này đã tồn tại!";
				}
				else
				{
					$db->query("UPDATE bds_menus SET menu_title = '".$title."', menu_url = '".$url."', menu_order = '".$order."' WHERE id = '".$id."'");
					$result["status"] = 200;
				}
			}
			else
			{
				$result["status"] = 404;
				$result["message"] = "Không tìm thấy nội dung này";
			}
		}
		echo json_encode($result);
	}
	public function deletemenu()
	{
		global $db;
		$result = array();
		$id = $_POST['id'];
		$db->query("SELECT * FROM bds_menus WHERE id = '".$id."'");
		if($db->num_row())
		{
			$db->query("DELETE FROM bds_menus WHERE id = '".$id."'");
			$result["status"] = 200;
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Không tìm thấy danh mục này!";
		}
		echo json_encode($result);
	}
	public function deletecat()
	{
		global $db;
		$result = array();
		$id = $_POST['id'];
		$db->query("SELECT * FROM bds_categories WHERE id = '".$id."'");
		if($db->num_row())
		{
			$db->query("DELETE FROM bds_categories WHERE id = '".$id."'");
			$result["status"] = 200;
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Không tìm thấy danh mục này!";
		}
		echo json_encode($result);
	}
	public function deletedepart()
	{
		global $db;
		$result = array();
		$id = $_POST['id'];
		$db->query("SELECT * FROM hicrm_departments WHERE id = '".$id."'");
		if($db->num_row())
		{
			$db->query("DELETE FROM hicrm_departments WHERE id = '".$id."'");
			$result["status"] = 200;
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Không tìm thấy phòng ban này!";
		}
		echo json_encode($result);
	}
	public function getplace()
	{
		global $db;
		$result = array();
		$db->query("SELECT * FROM bds_places WHERE id = '".$_POST['id']."' LIMIT 1");
		if($db->num_row())
		{
			$place = $db->fetch_object(true);
			$result["status"] = 200;
			$result["title"] = $place->place_name;
			$result["province"] = $place->place_province;
			$result["about"] = $place->place_about;
			$db->query("SELECT * FROM hicrm_districts WHERE district_province = '".$place->place_province."'");
			$districts = $db->fetch_object();
			$districttext = '';
			foreach($districts as $d)
			{
				$selected = ($d->id == $place->place_district)? "selected" : "";
				$districttext .= '<option value="'.$d->id.'" '.$selected.'>'.$d->district_name.'</option>';
			}
			$result["district"] = $districttext;
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Nội dung không tồn tại";
		}
		
		echo json_encode($result);
	}
	public function getproject()
	{
		global $db;
		$result = array();
		$db->query("SELECT * FROM bds_projects WHERE id = '".$_POST['id']."' LIMIT 1");
		if($db->num_row())
		{
			$place = $db->fetch_object(true);
			$result["status"] = 200;
			$result["title"] = $place->project_name;
			$result["holder"] = $place->project_holder;
			$result["address"] = $place->project_address;
			$result["area"] = $place->project_area;
			$result["price"] = $place->project_price;
			$result["scale"] = $place->project_scale;
			$result["category"] = $place->project_categories;
			$result["intro"] = $place->project_intro;
			$result["area_detail"] = $place->project_area_detail;
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Nội dung không tồn tại";
		}
		
		echo json_encode($result);
	}
	public function favorite()
	{
		$result = array();
		global $db;
		$id = $_POST['id'];
		if(isset($_SESSION["user"]["id"]) && $_SESSION["user"]["id"] != "")
		{
			$db->query("SELECT * FROM bds_user_favorites WHERE uid = '".$_SESSION['user']['id']."' AND pid = '".$id."'");
			if($db->num_row())
			{
				$db->query("DELETE FROM bds_user_favorites WHERE uid = '".$_SESSION['user']['id']."' AND pid = '".$id."'");
				$result["status"] = 200;
				$result["message"] = "Đã xóa tin này khỏi danh sách quan tâm";
				$result["url"] = XC_URL."/danh-sach-quan-tam.html";
			}
			else
			{
				$db->query("INSERT INTO bds_user_favorites(uid,pid) VALUES('".$_SESSION['user']['id']."','".$id."')");
				$result["status"] = 200;
				$result["message"] = "Đã thêm tin này vào danh sách quan tâm";
				$result["url"] = XC_URL."/danh-sach-quan-tam.html";
			}
		}
		else
		{
			$result["status"] = 503;
			$result["message"] = "Vui lòng đăng nhập để thực hiện thao tác này!";
			$result["url"] = XC_URL."/login";
		}
		echo json_encode($result);
	}
	public function changepass()
	{
		global $db;
		$result = array();
		$uid = $_SESSION['user']['id'];
		$oldpass = md5(mysql_real_escape_string($_POST["oldpass"]));
		$newpass = md5(mysql_real_escape_string($_POST["newpass"]));
		$db->query("SELECT * FROM hicrm_users WHERE id = '".$uid."' AND user_password = '".$oldpass."'");
		if($db->num_row())
		{
			$db->query("UPDATE hicrm_users SET user_password = '".$newpass."' WHERE id = '".$uid."'");
			$result["status"] = 200;
			unset($_SESSION["user"]);
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Mật khẩu cũ không đúng, vui lòng thử lại!";
		}
		echo json_encode($result);
	}
	public function upload()
	{
		$result = array();
		if(isset($_FILES['file']))
		{
			$errors= array();
			$file_name = $_FILES['file']['name'];
			$file_size =$_FILES['file']['size'];
			$file_tmp =$_FILES['file']['tmp_name'];
			$file_type=$_FILES['file']['type'];
			$file_ext=strtolower(end(explode('.',$_FILES['file']['name'])));
			$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['file']['name']); 
			$FinalFilenameFront = md5(time())."-".$FinalFilename;
			if(in_array($file_ext,$expensions)=== false){
				$errors[]="Extension not allowed, please choose a .pdf, .doc, .docx file.";
			}
			// Set the files size limit. Use this tool to convert the file size param https://www.thecalculator.co/others/File-Size-Converter-69.html
			if($file_size > 5242880){
				$errors[]='File size must be max 2Mb';
			}
			if(empty($errors)==true){
				move_uploaded_file($file_tmp,"./uploads/post/".$FinalFilenameFront);
				$result["status"] = 200;
				$result["url"] = XC_URL."/uploads/post/".$FinalFilenameFront;
				$result["id"] = $FinalFilenameFront;
			}else
			{
				$result["status"] = 500;
			}
			
		}
		echo json_encode($result);
	}
	public function uploadavatar()
	{
		global $db;
		$result = array();
		if(isset($_FILES['file']))
		{
			$errors= array();
			$file_name = $_FILES['file']['name'];
			$file_size =$_FILES['file']['size'];
			$file_tmp =$_FILES['file']['tmp_name'];
			$file_type=$_FILES['file']['type'];
			$file_ext=strtolower(end(explode('.',$_FILES['file']['name'])));
			$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['file']['name']); 
			$FinalFilenameFront = md5(time())."-".$FinalFilename;
			if(in_array($file_ext,$expensions)=== false){
				$errors[]="Extension not allowed, please choose a .pdf, .doc, .docx file.";
			}
			// Set the files size limit. Use this tool to convert the file size param https://www.thecalculator.co/others/File-Size-Converter-69.html
			if($file_size > 5242880){
				$errors[]='File size must be max 2Mb';
			}
			if(empty($errors)==true){
				move_uploaded_file($file_tmp,"./uploads/users/".$FinalFilenameFront);
				$db->query("UPDATE hicrm_users SET user_avatar = '".$FinalFilenameFront."' WHERE id = '".$_SESSION['user']['id']."'");
				$result["status"] = 200;
				$result["url"] = XC_URL."/uploads/users/".$FinalFilenameFront;
				$result["id"] = $FinalFilenameFront;
			}else
			{
				$result["status"] = 500;
			}
			
		}
		echo json_encode($result);
	}
	public function uploadslider()
	{
		global $db;
		$result = array();
		if(isset($_FILES['file']))
		{
			$errors= array();
			$file_name = $_FILES['file']['name'];
			$file_size =$_FILES['file']['size'];
			$file_tmp =$_FILES['file']['tmp_name'];
			$file_type=$_FILES['file']['type'];
			$file_ext=strtolower(end(explode('.',$_FILES['file']['name'])));
			$OriginalFilename = $FinalFilename = preg_replace('`[^a-z0-9-_.]`i','',$_FILES['file']['name']); 
			$FinalFilenameFront = md5(time())."-".$FinalFilename;
			if(in_array($file_ext,$expensions)=== false){
				$errors[]="Extension not allowed, please choose a .pdf, .doc, .docx file.";
			}
			// Set the files size limit. Use this tool to convert the file size param https://www.thecalculator.co/others/File-Size-Converter-69.html
			if($file_size > 5242880){
				$errors[]='File size must be max 2Mb';
			}
			if(empty($errors)==true){
				move_uploaded_file($file_tmp,"./uploads/general/".$FinalFilenameFront);
				$db->query("UPDATE bds_configs SET config_value = '".$FinalFilenameFront."' WHERE config_key = 'slider_image'");
				$result["status"] = 200;
				$result["url"] = XC_URL."/uploads/general/".$FinalFilenameFront;
				$result["id"] = $FinalFilenameFront;
			}else
			{
				$result["status"] = 500;
			}
			
		}
		echo json_encode($result);
	}
	public function getdistrict()
	{
		$result = array();
		global $db;
		$pid = $_POST['pid'];
		$db->query("SELECT * FROM hicrm_districts WHERE district_province = '".$pid."'");
		if($db->num_row())
		{
			$districts = $db->fetch_object();
			$data = '';
			foreach($districts as $d)
			{
				$data .= '<option value="'.$d->id.'">'.$d->district_name.'</option>';
			}
			$result["status"] = 200;
			$result["data"] = $data;
		}
		else
		{
			$result["status"] = 500;
			$result["message"] = "Không tồn tại tỉnh/thành";
		}
		echo json_encode($result);
	}
	public function getward()
	{
		$result = array();
		global $db;
		$did = $_POST['did'];
		$db->query("SELECT * FROM hicrm_wards WHERE ward_district = '".$did."'");
		if($db->num_row())
		{
			$districts = $db->fetch_object();
			$data = '';
			foreach($districts as $d)
			{
				$data .= '<option value="'.$d->id.'">'.$d->ward_name.'</option>';
			}
			$result["status"] = 200;
			$result["data"] = $data;
		}
		else
		{
			$result["status"] = 500;
			//$result["message"] = "Không tồn tại quận/huyện";
		}
		echo json_encode($result);
	}
	public function post()
	{
		global $db;
		$result = array();
		$code = general::getInstance()->generateid("transaction");
		$price = str_replace('.','',$_POST['tonggia']);
		$metas = $_POST['metas'];
		//$metas = json_decode($metas);
		$db->query("INSERT INTO bds_posts(post_code, post_title,post_content,post_type,post_author,post_category,post_price,post_ward,post_district,post_province,post_featured,post_update_time,post_view,post_status,post_appoved_by,post_video,post_title_en,post_content_en) VALUES('".$code."','".$_POST['title']."','".$_POST['noidung']."','".$_POST['type']."','".$_SESSION['user']['id']."','".$_POST['category']."','".$price."','".$_POST['xa']."','".$_POST['huyen']."','".$_POST['tinh']."',1,'".date("Y-m-d H:i:s")."',0,0,1,'".$_POST['video']."','".$_POST['title_en']."','".$_POST['noidung_en']."')");
		$db->query("SELECT id FROM bds_posts WHERE post_code = '".$code."' ORDER BY post_create_time DESC LIMIT 1");
		$postid = $db->fetch_object(true)->id;
		if(isset($_POST['dongia']) && $_POST['dongia'] != "")
		{
			$db->query("INSERT INTO bds_post_metas(pid,meta_key,meta_value) VALUES('".$postid."','DON_GIA','".$_POST['dongia']."')");
		}
		
		foreach ($metas as $mt)
		{
			$text = explode("|",$mt);
			//$textdata .= $mt." - ssss -";
			$db->query("INSERT INTO bds_post_metas(pid,meta_key,meta_value) VALUES('".$postid."','".$text[0]."','".$text[1]."')");
		}
		/*
		if(isset($_POST['duongvao']) && $_POST['duongvao'] != "")
		{
			$db->query("INSERT INTO bds_post_metas(pid,meta_key,meta_value) VALUES('".$postid."','DUONG_VAO','".$_POST['duongvao']."')");
		}
		if(isset($_POST['mattien']) && $_POST['mattien'] != "")
		{
			$db->query("INSERT INTO bds_post_metas(pid,meta_key,meta_value) VALUES('".$postid."','MAT_TIEN','".$_POST['mattien']."')");
		}
		if(isset($_POST['sophong']) && $_POST['sophong'] != "")
		{
			$db->query("INSERT INTO bds_post_metas(pid,meta_key,meta_value) VALUES('".$postid."','SO_PHONG','".$_POST['sophong']."')");
		}
		if(isset($_POST['sophongwc']) && $_POST['sophongwc'] != "")
		{
			$db->query("INSERT INTO bds_post_metas(pid,meta_key,meta_value) VALUES('".$postid."','SO_PHONG_WC','".$_POST['sophongwc']."')");
		}
		if(isset($_POST['dien_tich']) && $_POST['dien_tich'] != "")
		{
			$db->query("INSERT INTO bds_post_metas(pid,meta_key,meta_value) VALUES('".$postid."','DIEN_TICH','".$_POST['dien_tich']."')");
		}
		*/
		if(isset($_POST['hinhanh']) && count($_POST['hinhanh']))
		{
			$hinhanh = $_POST['hinhanh'];
			$i = 0;
			foreach($hinhanh as $img)
			{
				$f = ($i == 0)? 1 : 0;
				$db->query("INSERT INTO bds_images(pid,image_url,image_feature) VALUES('".$postid."','".$img."','".$f."')");
				$i++;
			}
		}
		$result["status"] = 200;
		$result["code"] = $code;
		$result["data"]["data"] = $_POST['metas'];
		$result["data"]["post"] = $_POST;
		echo json_encode($result);
	}
	public function editpost()
	{
		global $db;
		$result = array();
		$id = $_POST['id'];
		//$code = general::getInstance()->generateid("transaction");
		$price = str_replace('.','',$_POST['tonggia']);
		if($_POST['title'] != ""){ $db->query("UPDATE bds_posts SET post_title = '".$_POST['title']."' WHERE id = '".$id."'"); }
		if($_POST['title_en'] != ""){ $db->query("UPDATE bds_posts SET post_title_en = '".$_POST['title_en']."' WHERE id = '".$id."'"); }
		if($_POST['noidung'] != ""){ $db->query("UPDATE bds_posts SET post_content = '".$_POST['noidung']."' WHERE id = '".$id."'"); }
		if($_POST['noidung_en'] != ""){ $db->query("UPDATE bds_posts SET post_content_en = '".$_POST['noidung_en']."' WHERE id = '".$id."'"); }
		if($_POST['type'] != ""){ $db->query("UPDATE bds_posts SET post_type = '".$_POST['type']."' WHERE id = '".$id."'"); }
		if($_POST['category'] != ""){ $db->query("UPDATE bds_posts SET post_category = '".$_POST['category']."' WHERE id = '".$id."'"); }
		if($price != 0){ $db->query("UPDATE bds_posts SET post_price = '".$price."' WHERE id = '".$id."'"); }
		if($_POST['xa'] != ""){ $db->query("UPDATE bds_posts SET post_ward = '".$_POST['xa']."' WHERE id = '".$id."'"); }
		if($_POST['huyen'] != ""){ $db->query("UPDATE bds_posts SET post_district = '".$_POST['huyen']."' WHERE id = '".$id."'"); }
		if($_POST['tinh'] != ""){ $db->query("UPDATE bds_posts SET post_province = '".$_POST['tinh']."' WHERE id = '".$id."'"); }
		if($_POST['video'] != ""){ $db->query("UPDATE bds_posts SET post_video = '".$_POST['video']."' WHERE id = '".$id."'"); }
		
		//$db->query("INSERT INTO bds_posts(post_code, post_title,post_content,post_type,post_author,post_category,post_price,post_ward,post_district,post_province,post_featured,post_update_time,post_view,post_status,post_appoved_by) VALUES('".$code."','".$_POST['title']."','".$_POST['noidung']."','".$_POST['type']."','".$_SESSION['user']['id']."','".$_POST['category']."','".$price."','".$_POST['xa']."','".$_POST['huyen']."','".$_POST['tinh']."',1,'".date("Y-m-d H:i:s")."',0,0,1)");
		//$db->query("SELECT id FROM bds_posts WHERE post_code = '".$code."' ORDER BY post_create_time DESC LIMIT 1");
		$postid = $id;
		if(isset($_POST['dongia']) && $_POST['dongia'] != "")
		{
			$db->query("UPDATE bds_post_metas SET meta_value = '".$_POST['dongia']."' WHERE pid = '".$postid."' AND meta_key = 'DON_GIA'");
		}
		if(isset($_POST['duongvao']) && $_POST['duongvao'] != "")
		{
			$db->query("UPDATE bds_post_metas SET meta_value = '".$_POST['duongvao']."' WHERE pid = '".$postid."' AND meta_key = 'DUONG_VAO'");
		}
		if(isset($_POST['mattien']) && $_POST['mattien'] != "")
		{
			$db->query("UPDATE bds_post_metas SET meta_value = '".$_POST['mattien']."' WHERE pid = '".$postid."' AND meta_key = 'MAT_TIEN'");
		}
		if(isset($_POST['sophong']) && $_POST['sophong'] != "")
		{
			$db->query("UPDATE bds_post_metas SET meta_value = '".$_POST['sophong']."' WHERE pid = '".$postid."' AND meta_key = 'SO_PHONG'");
		}
		if(isset($_POST['sophongwc']) && $_POST['sophongwc'] != "")
		{
			$db->query("UPDATE bds_post_metas SET meta_value = '".$_POST['sophongwc']."' WHERE pid = '".$postid."' AND meta_key = 'SO_PHONG_WC'");
		}
		if(isset($_POST['dien_tich']) && $_POST['dien_tich'] != "")
		{
			$db->query("UPDATE bds_post_metas SET meta_value = '".$_POST['dien_tich']."' WHERE pid = '".$postid."' AND meta_key = 'DIEN_TICH'");
		}
		if(isset($_POST['hinhanh']) && count($_POST['hinhanh']))
		{
			$db->query("DELETE FROM bds_images WHERE pid = '".$id."'");
			$hinhanh = $_POST['hinhanh'];
			$i = 0;
			foreach($hinhanh as $img)
			{
				$f = ($i == 0)? 1 : 0;
				$db->query("INSERT INTO bds_images(pid,image_url,image_feature) VALUES('".$postid."','".$img."','".$f."')");
				$i++;
			}
		}
		$result["status"] = 200;
		$result["code"] = $code;
		$result["data"] = $_POST;
		echo json_encode($result);
	}
	public function testval()
	{
		$price = "55.000.000";
		echo floatval($price);
	}
	public function getmetadata()
	{
		$category = $_POST['cat'];
		$result = array();
		global $db;
		$db->query("SELECT * FROM bds_meta_datas as md
		LEFT JOIN bds_meta_type as mt ON md.meta_data = mt.id
		WHERE post_category = '".$category."'");
		$result["status"] = 200;
		$datatext = '';
		$listmeta = $db->fetch_object();
		foreach($listmeta as $meta)
		{
			$datatext .= '<div class="col-md-6">
                                       <div class="form-group">
                                          <label>'.$meta->meta_name.' ('.$meta->meta_unit.')</label>
                                          <input type="text" maxlength="4" class="fmfloat form-control form-control-sm" name="metadata[]" id="metadata[]" data-key="'.$meta->meta_key.'" value="">
                                       </div>
                                    </div>';
		}
		$result["data"] = $datatext;
		echo json_encode($result);
	}
	public function getmetadatacat()
	{
		global $db;
		$result = array();
		$depart = $_POST['id'];
		$db->query("SELECT permission_id FROM hicrm_permission_datas WHERE depart = '".$depart."'");
		$list = $db->fetch_object();
		$metaa = array();
		foreach($list as $meta)
		{
			array_push($metaa,$meta->permission_id);
		}
		$listmeta = home::getInstance()->get_list_permission();
		$text = '';
		foreach($listmeta as $per)
		{
			$selected = (in_array($per->id,$metaa))? "checked=checked" : "";
			$text .= '<div class="col-3">
								<div class="form-check">
									<input class="form-check-input" '.$selected.' type="checkbox" value="'.$per->id.'" id="'.$per->id.'" name="metadata[]">
									<label class="form-check-label" for="'.$per->id.'">'.$per->permission_name.'</label>
								</div>
							</div>';
		}
		$result["status"] = 200;
		$result["data"] = $text;
		echo json_encode($result);
	}
	public function getmetabycategory()
	{
		global $db;
		$result = array();
		$catid = $_POST['id'];
		$db->query("SELECT meta_data FROM bds_meta_datas WHERE post_category = '".$catid."'");
		$list = $db->fetch_object();
		$metaa = array();
		foreach($list as $meta)
		{
			array_push($metaa,$meta->meta_data);
		}
		$result["status"] = 200;
		$result["data"] = $metaa;
		echo json_encode($result);
	}
	public function updatecatmeta()
	{
		global $db;
		$result = array();
		$catid = $_POST['id'];
		$meta = $_POST['data'];
		$db->query("DELETE FROM hicrm_permission_datas WHERE depart = '".$catid."'");
		foreach($meta as $m)
		{
			$db->query("INSERT INTO hicrm_permission_datas(depart,permission_id) VALUES('".$catid."','".$m."')");
		}
		$result["status"] = 200;
		$result["data"] = $meta;
		echo json_encode($result);
	}
	public function setlanguage()
	{
		$result = array();
		$_SESSION['lang'] = $_POST['lang'];
		$result["status"] = 200;
		echo json_encode($result);
	}
	
	//AGENCY API
	public function ApproveAgency()
	{
		global $db;
		$result = array();
		$db->query("SELECT * FROM ow_users WHERE id = '".$_POST['id']."' AND user_is_agency = 2");
		if($db->num_row())
		{
			$db->query("UPDATE ow_users SET user_is_agency = 1 WHERE id = '".$_POST['id']."'");
			$result["status"] = 200;
		}
		else
		{
			$result["status"] = 404;
			$result["message"] = "Không tìm thấy tài khoản!";
		}
		echo json_encode($result);
	}
	public function DenineAgency()
	{
		global $db;
		$result = array();
		$db->query("SELECT * FROM ow_users WHERE id = '".$_POST['id']."' AND user_is_agency = 2");
		if($db->num_row())
		{
			$db->query("UPDATE ow_users SET user_is_agency = 0 WHERE id = '".$_POST['id']."'");
			$result["status"] = 200;
		}
		else
		{
			$result["status"] = 404;
			$result["message"] = "Không tìm thấy tài khoản!";
		}
		echo json_encode($result);
	}
	public function DepositeCustomer()
	{
		global $db;
		$result = array();
		$uid = $_POST['uid'];
		$hash = $_POST['hash'];
		$amount = $_POST['amount'];
		$note = $_POST['note'];
		$code = $_POST['code'];
		$staffid = $_SESSION['staff']['id'];
		$db->query("SELECT * FROM ow_transactions WHERE trans_hash = '".$hash."'");
		if($db->num_row())
		{
			$result["status"] = 500;
			$result["message"] = "Đã tồn tại giao dịch!";
		}
		else
		{
			$transcode = general::getInstance()->generateid("transaction");
			$sql = "INSERT INTO ow_transactions(uid,trans_code,trans_type,trans_bank,trans_method,trans_amount,trans_hash,trans_status,trans_note,trans_data,trans_approved_by,trans_approved_date) VALUES('".$uid."','".$transcode."','1','1','1','".$amount."','".$hash."','2','".$note."','".$code."','".$_SESSION['staff']['id']."','".date("Y-m-d H:i:s")."')";
			$db->query($sql);
			$sql = "UPDATE ow_users SET user_available_wallet = user_available_wallet + ".$amount." WHERE id = '".$uid."'";
			$db->query($sql);
			//$db->query("UPDATE ow_transactions SET trans_status = 2, trans_approved_by = '".$_SESSION['staff']['id']."', trans_approved_date = '".date("Y-m-d H:i:s")."' WHERE id = '".$tid."'");
			$result["status"] = 200;
		}
		echo json_encode($result);
	}
	//AGENCY API
/// Start Code Cuong
// API Student register
public function addstudent()
{
	// if(!$this->requireAdminApiPermission('students', false)){ return; }
		global $db;

		header('Content-Type: application/json; charset=utf-8');

		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			echo json_encode(array(
				'success' => false,
				'status' => 405,
				'message' => 'Phương thức không hợp lệ.'
			));
			return;
		}

		$studentCodeRaw = isset($_POST['student_code']) ? trim($_POST['student_code']) : '';
		$studentNameRaw = isset($_POST['student_name']) ? trim($_POST['student_name']) : '';
		$action = isset($_POST['action']) ? trim($_POST['action']) : 'search';

		if ($studentCodeRaw === '' || $studentNameRaw === '') {
			echo json_encode(array(
				'success' => false,
				'status' => 400,
				'message' => 'Vui lòng nhập họ tên và mã sinh viên.'
			));
			return;
		}

		if (!in_array($action, array('search', 'create'), true)) {
			echo json_encode(array(
				'success' => false,
				'status' => 400,
				'message' => 'Thao tác không hợp lệ.'
			));
			return;
		}

		$studentCode = $db->escapestring($studentCodeRaw);
		$studentName = $db->escapestring($studentNameRaw);

		$db->query("
			SELECT * FROM hicrm_student_profile
			WHERE student_code = '".$studentCode."'
			  AND student_name = '".$studentName."'
			LIMIT 1
		");

		if (!$db->num_row()) {
			echo json_encode(array(
				'success' => false,
				'status' => 404,
				'step' => 'not_found',
				'message' => 'Không tìm thấy sinh viên.'
			));
			return;
		}

		$student = $db->fetch_object(true);

		if ($action === 'search') {
			echo json_encode(array(
				'success' => true,
				'status' => 200,
				'step' => 'found',
				'message' => 'Tìm thấy sinh viên.',
				'student_id' => $student->id,
				'student_code' => $studentCodeRaw,
				'student_name' => $studentNameRaw
			));
			return;
		}

		$db->query("
			SELECT id FROM hicrm_users
			WHERE user_username = '".$studentCode."'
			LIMIT 1
		");

		if ($db->num_row()) {
			echo json_encode(array(
				'success' => false,
				'status' => 409,
				'step' => 'exists',
				'message' => 'Tài khoản sinh viên đã tồn tại.'
			));
			return;
		}

		$username = $studentCode;
		$rawPassword = $this->generateStudentPassword(10);
		$user_password = md5($rawPassword);
		$user_email_raw = !empty($student->student_email) ? trim($student->student_email) : '';
		$user_email = $user_email_raw !== '' ? $db->escapestring($user_email_raw) : $studentCode.'@sv.cdkt';
		$full_name = $db->escapestring($student->student_name);
		$user_phone = !empty($student->student_phone) ? $db->escapestring($student->student_phone) : '';
		$student_id = $student->id;
		$registerTime = date('Y-m-d H:i:s');

		$db->query("INSERT INTO hicrm_users(
			student_id,
			user_username,
			full_name,
			user_email,
			user_phone,
			user_password,
			user_group,
			user_status,
			user_avatar_url,
			user_reset_token,
			user_reset_token_expires,
			user_two_fa_enabled,
			user_two_fa_secret,
			user_two_fa_method,
			user_email_verified_at,
			user_email_verify_token,
			user_last_login_at,
			user_last_login_ip,
			user_created_at,
			user_updated_at,
			user_deleted_at,
			user_is_subscribed
		) VALUES (
			'".$student_id."',
			'".$username."',
			'".$full_name."',
			'".$user_email."',
			'".$user_phone."',
			'".$user_password."',
			'3',
			'1',
			'',
			NULL,
			NULL,
			'0',
			NULL,
			NULL,
			NULL,
			NULL,
			NULL,
			NULL,
			'".$registerTime."',
			'".$registerTime."',
			NULL,
			'0'
		)");

		$db->query("SELECT id FROM hicrm_users WHERE user_username = '".$studentCode."' LIMIT 1");

		if (!$db->num_row()) {
			echo json_encode(array(
				'success' => false,
				'status' => 500,
				'step' => 'create_failed',
				'message' => 'Không thể tạo tài khoản, vui lòng thử lại sau.'
			));
			return;
		}

		$db->query("UPDATE hicrm_student_profile SET student_is_register = '1' WHERE id = '".$student_id."'");

		$emailSent = false;
		$emailMessage = '';
		$emailError = '';

		// if ($user_email_raw !== '' && filter_var($user_email_raw, FILTER_VALIDATE_EMAIL)) {
		// 	$emailSent = $this->mail->sendStudentPasswordEmail($student->student_name, $user_email_raw, $username, $rawPassword, 'Thông tin tài khoản đăng nhập hệ thống Cổg thông tin việc làm Trường Cao đẳng Kon Tum');
		// 	$emailError = isset($this->studentMailError) ? $this->studentMailError : '';
		// 	$emailMessage = $emailSent
		// 		? 'Mật khẩu đã được gửi đến email '.$this->maskEmail($user_email_raw).'.'
		// 		: 'Tài khoản đã được tạo nhưng chưa thể gửi email. Vui lòng kiểm tra cấu hình SMTP.';
		// } else {
		// 	$emailMessage = ' Sinh viên chưa có email hợp lệ, vì vậy không thể gửi mật khẩu qua email.';
		// }

		echo json_encode(array(
			'success' => true,
			'status' => 200,
			'step' => 'success',
			'message' => 'Khởi tạo tài khoản thành công.'.$emailMessage,
			'description' => 'Vui lòng liên hệ giáo viên chủ nhiệm để nhận thông tin đăng nhập.'
			// 'username' => $studentCodeRaw,
			// 'email' => $user_email_raw !== '' ? $this->maskEmail($user_email_raw) : '',
			// 'email_sent' => $emailSent,
			// 'mail_error' => $emailError
		));
		return;
	}

	public function insertStudens()
	{
		global $db;

		header('Content-Type: application/json; charset=utf-8');

		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			echo json_encode(array(
				'success' => false,
				'status' => 405,
				'message' => 'Phương thức không hợp lệ.'
			));
			return;
		}

		if (!isset($_FILES['student_file']) || empty($_FILES['student_file']['tmp_name'])) {
			echo json_encode(array(
				'success' => false,
				'status' => 400,
				'message' => 'Vui lòng chọn file Excel hoặc CSV để nhập.'
			));
			return;
		}

		$file = $_FILES['student_file'];
		$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		$parsed = $this->parseStudentImportFile($file['tmp_name'], $extension);

		if ($parsed['error'] !== '') {
			echo json_encode(array(
				'success' => false,
				'status' => 400,
				'message' => $parsed['error']
			));
			return;
		}

		$rows = $parsed['rows'];
		if (empty($rows) || count($rows) < 2) {
			echo json_encode(array(
				'success' => false,
				'status' => 400,
				'message' => 'File trống hoặc không có dữ liệu hợp lệ.'
			));
			return;
		}

		$header = array_change_key_case(array_map('trim', $rows[0]), CASE_LOWER);
		$allowedColumns = array(
			'student_code',
			'student_name',
			'student_phone',
			'student_email',
			'student_class',
			'student_birthday',
			'student_gender',
			'student_major_id',
			'student_gpa',
			'student_rank',
			'student_description'
		);
		$requiredColumns = array(
			'student_code',
			'student_name',
			'student_class',
			'student_birthday',
			'student_gender',
			'student_major_id'
		);
		$missingColumns = array_values(array_diff($requiredColumns, $header));
		if (!empty($missingColumns)) {
			echo json_encode(array(
				'success' => false,
				'status' => 400,
				'message' => 'Thiếu cột bắt buộc trong file import.',
				'errors' => array('Thiếu cột: '.implode(', ', $missingColumns).'.')
			));
			return;
		}

		$majorMap = $this->getStudentMajorReferenceMap();
		$fileStudentCodes = array();

		$inserted = 0;
		$skipped = 0;
		$errors = array();

		for ($rowIndex = 1; $rowIndex < count($rows); $rowIndex++) {
			$row = $rows[$rowIndex];
			if (!is_array($row)) {
				continue;
			}

			$record = array_fill_keys($allowedColumns, '');
			foreach ($header as $columnIndex => $columnName) {
				if (!in_array($columnName, $allowedColumns, true)) {
					continue;
				}
				$record[$columnName] = isset($row[$columnIndex]) ? trim($row[$columnIndex]) : '';
			}

			$displayRow = $rowIndex + 1;
			$studentCode = trim($record['student_code']);
			$studentName = trim($record['student_name']);
			$rowLabel = $this->formatStudentImportRowLabel($displayRow, $studentCode, $studentName);
			$rowErrors = array();

			if ($studentCode === '') {
				$rowErrors[] = 'cột student_code bắt buộc';
			}
			if ($studentName === '') {
				$rowErrors[] = 'cột student_name bắt buộc';
			}

			if ($studentCode !== '') {
				$studentCodeKey = strtolower($studentCode);
				if (isset($fileStudentCodes[$studentCodeKey])) {
					$rowErrors[] = 'student_code bị trùng trong file import';
				} else {
					$fileStudentCodes[$studentCodeKey] = true;
				}
			}

			$studentClassRaw = trim($record['student_class']);
			if ($studentClassRaw === '') {
				$rowErrors[] = 'cột student_class bắt buộc';
			}

			$studentBirthdayRaw = trim($record['student_birthday']);
			if ($studentBirthdayRaw === '') {
				$rowErrors[] = 'cột student_birthday bắt buộc';
				$studentBirthday = '1970-01-01';
			} else {
				$birthdayObj = DateTime::createFromFormat('Y-m-d', $studentBirthdayRaw);
				$birthdayErrors = DateTime::getLastErrors();
				$birthdayHasErrors = is_array($birthdayErrors) && (($birthdayErrors['warning_count'] > 0) || ($birthdayErrors['error_count'] > 0));
				if (!$birthdayObj || $birthdayHasErrors) {
					$rowErrors[] = 'student_birthday phải đúng định dạng YYYY-MM-DD';
					$studentBirthday = '1970-01-01';
				} else {
					$studentBirthday = $birthdayObj->format('Y-m-d');
				}
			}

			$studentGenderRaw = trim($record['student_gender']);
			if ($studentGenderRaw === '') {
				$rowErrors[] = 'cột student_gender bắt buộc';
				$studentGender = 0;
			} elseif (!preg_match('/^\d+$/', $studentGenderRaw)) {
				$rowErrors[] = 'student_gender phải là số nguyên';
				$studentGender = 0;
			} else {
				$studentGender = (int) $studentGenderRaw;
				if (!in_array($studentGender, array(0, 1, 2), true)) {
					$rowErrors[] = 'student_gender chỉ nhận các giá trị 0, 1, 2';
				}
			}

			$studentMajorRaw = trim($record['student_major_id']);
			if ($studentMajorRaw === '') {
				$rowErrors[] = 'cột student_major_id bắt buộc';
				$studentMajorId = 0;
			} elseif (!preg_match('/^\d+$/', $studentMajorRaw)) {
				$rowErrors[] = 'student_major_id phải là số nguyên';
				$studentMajorId = 0;
			} else {
				$studentMajorId = (int) $studentMajorRaw;
				if (!isset($majorMap[$studentMajorId])) {
					$rowErrors[] = 'student_major_id không tồn tại trong danh mục';
				}
			}

			$studentEmailRaw = trim($record['student_email']);
			if ($studentEmailRaw !== '' && !filter_var($studentEmailRaw, FILTER_VALIDATE_EMAIL)) {
				$rowErrors[] = 'student_email không đúng định dạng email';
			}

			$studentPhoneRaw = trim($record['student_phone']);
			if ($studentPhoneRaw !== '' && !preg_match('/^[0-9+\-\s().]{8,20}$/', $studentPhoneRaw)) {
				$rowErrors[] = 'student_phone không đúng định dạng số điện thoại';
			}

			$studentGpaRaw = trim($record['student_gpa']);
			if ($studentGpaRaw === '') {
				$studentGpa = null;
			} elseif (!is_numeric($studentGpaRaw)) {
				$rowErrors[] = 'student_gpa phải là số';
				$studentGpa = null;
			} else {
				$studentGpa = (float) $studentGpaRaw;
				if ($studentGpa < 0 || $studentGpa > 10) {
					$rowErrors[] = 'student_gpa phải nằm trong khoảng 0 đến 10';
				}
			}

			if (!empty($rowErrors)) {
				$skipped++;
				$errors[] = $rowLabel.': '.implode('; ', $rowErrors).'.';
				continue;
			}

			$db->query("SELECT id FROM hicrm_student_profile WHERE student_code = '".$db->escapestring($studentCode)."' LIMIT 1");
			if ($db->num_row()) {
				$skipped++;
				$errors[] = $rowLabel.': mã sinh viên đã tồn tại trong hệ thống.';
				continue;
			}

			$studentGpaValue = $studentGpa !== null ? number_format($studentGpa, 2, '.', '') : null;
			$studentRank = $db->escapestring($record['student_rank']);
			$studentDescription = $db->escapestring($record['student_description']);

			$studentPhone = $db->escapestring($studentPhoneRaw);
			$studentEmail = $db->escapestring($studentEmailRaw);
			$studentClassValue = $db->escapestring($studentClassRaw);

			$db->query("INSERT INTO hicrm_student_profile (student_code, student_name, student_phone, student_email, student_class, student_birthday, student_gender, student_major_id, student_gpa, student_rank, student_description) VALUES ('".$db->escapestring($studentCode)."', '".$db->escapestring($studentName)."', '".$studentPhone."', '".$studentEmail."', '".$studentClassValue."', '".$db->escapestring($studentBirthday)."', '".$studentGender."', '".$studentMajorId."', ".($studentGpaValue !== null ? "'".$studentGpaValue."'" : 'NULL').", '".$studentRank."', '".$studentDescription."')");
			$inserted++;
		}

		echo json_encode(array(
			'success' => true,
			'status' => 200,
			'inserted' => $inserted,
			'skipped' => $skipped,
			'errors' => $errors,
			'message' => 'Import hoàn tất.'
		));
	}

	public function insertStudents()
	{
		if(!$this->requireAdminApiPermission('students', false)){ return; }
		return $this->insertStudens();
	}

	private function formatStudentImportRowLabel($displayRow, $studentCode, $studentName)
	{
		$parts = array();
		$studentCode = trim((string)$studentCode);
		$studentName = trim((string)$studentName);
		if ($studentCode !== '') {
			$parts[] = 'MSSV '.$studentCode;
		}
		if ($studentName !== '') {
			$parts[] = 'Tên '.$studentName;
		}
		return 'Dòng '.$displayRow.(empty($parts) ? '' : ' ('.implode(' - ', $parts).')');
	}

	private function parseStudentImportFile($filePath, $extension)
	{
		$rows = array();
		$error = '';
		if ($extension === 'csv') {
			$rows = $this->parseCsvFile($filePath);
		} elseif ($extension === 'xlsx') {
			$rows = $this->parseXlsxFile($filePath);
		} elseif ($extension === 'xls' || $extension === 'xml') {
			if ($extension === 'xls' && $this->isBinaryExcelFile($filePath)) {
				$error = 'File .xls hiện tại là định dạng Excel nhị phân cũ, hệ thống chưa đọc trực tiếp được. Vui lòng mở file và lưu lại thành .xlsx, hoặc dùng file mẫu import từ hệ thống.';
			} else {
				$rows = $this->parseSpreadsheetXmlFile($filePath);
			}
		} else {
			$error = 'Chỉ chấp nhận file .xls, .xml, .xlsx hoặc .csv.';
		}

		if ($error === '' && empty($rows)) {
			$error = 'File trống hoặc không đọc được dữ liệu import.';
		}

		return array(
			'rows' => $rows,
			'error' => $error
		);
	}

	private function getStudentMajorReferenceMap()
	{
		global $db;
		$map = array();
		$db->query("SELECT id, job_category_name FROM hicrm_job_categories ORDER BY job_category_name ASC, id ASC");
		$rows = $db->fetch_object();
		if (is_array($rows)) {
			foreach ($rows as $row) {
				$map[(int) $row->id] = isset($row->job_category_name) ? $row->job_category_name : '';
			}
		}
		return $map;
	}

	private function parseCsvFile($filePath, $delimiter = ',')
	{
		$rows = array();
		if (!is_readable($filePath)) {
			return $rows;
		}
		if (($handle = fopen($filePath, 'r')) === false) {
			return $rows;
		}
		$firstLine = fgets($handle);
		if ($firstLine === false) {
			fclose($handle);
			return $rows;
		}
		$firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
		$delimiter = $this->detectCsvDelimiter($firstLine, $delimiter);
		rewind($handle);
		while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
			if (isset($data[0])) {
				$data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$data[0]);
			}
			$rows[] = $data;
		}
		fclose($handle);
		return $rows;
	}

	private function parseSpreadsheetXmlFile($filePath)
	{
		$rows = array();
		if (!function_exists('simplexml_load_file')) {
			return $rows;
		}

		$xml = @simplexml_load_file($filePath);
		if (!$xml) {
			return $rows;
		}

		$xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
		$worksheets = $xml->xpath('//ss:Worksheet');
		if (!is_array($worksheets) || empty($worksheets)) {
			return $rows;
		}

		$dataSheet = null;
		foreach ($worksheets as $worksheet) {
			$attrs = $worksheet->attributes('urn:schemas-microsoft-com:office:spreadsheet');
			$sheetName = isset($attrs['Name']) ? (string) $attrs['Name'] : '';
			if ($sheetName === 'DuLieuImport') {
				$dataSheet = $worksheet;
				break;
			}
		}
		if ($dataSheet === null) {
			$dataSheet = $worksheets[0];
		}

		$rowNodes = $dataSheet->xpath('./ss:Table/ss:Row');
		if (!is_array($rowNodes) || empty($rowNodes)) {
			return $rows;
		}

		foreach ($rowNodes as $rowNode) {
			$cellNodes = $rowNode->xpath('./ss:Cell');
			$current = array();
			if (is_array($cellNodes)) {
				foreach ($cellNodes as $cellNode) {
					$dataNodes = $cellNode->xpath('./ss:Data');
					$current[] = isset($dataNodes[0]) ? trim((string) $dataNodes[0]) : '';
				}
			}
			$rows[] = $current;
		}

		return $rows;
	}

	private function parseXlsxFile($filePath)
	{
		$rows = array();
		if (!class_exists('ZipArchive')) {
			return $rows;
		}

		$zip = new ZipArchive();
		if ($zip->open($filePath) !== true) {
			return $rows;
		}

		$mainNs = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
		$relNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
		$sharedStrings = array();
		if (($index = $zip->locateName('xl/sharedStrings.xml')) !== false) {
			$sharedXml = $zip->getFromIndex($index);
			if ($sharedXml !== false) {
				$shared = @simplexml_load_string($sharedXml);
				if ($shared) {
					$sharedChildren = $shared->children($mainNs);
					if (isset($sharedChildren->si)) {
						foreach ($sharedChildren->si as $si) {
							$siChildren = $si->children($mainNs);
							$value = '';
							if (isset($siChildren->t)) {
								$value = (string)$siChildren->t;
							} elseif (isset($siChildren->r)) {
								foreach ($siChildren->r as $r) {
									$rChildren = $r->children($mainNs);
									$value .= isset($rChildren->t) ? (string)$rChildren->t : '';
								}
							}
							$sharedStrings[] = $value;
						}
					}
				}
			}
		}

		$sheetPath = '';
		$workbookXml = $zip->getFromName('xl/workbook.xml');
		$workbookRelsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
		if ($workbookXml !== false && $workbookRelsXml !== false) {
			$workbook = @simplexml_load_string($workbookXml);
			$rels = @simplexml_load_string($workbookRelsXml);
			if ($workbook && $rels) {
				$relMap = array();
				$relChildren = $rels->children('http://schemas.openxmlformats.org/package/2006/relationships');
				if (isset($relChildren->Relationship)) {
					foreach ($relChildren->Relationship as $relationship) {
						$attributes = $relationship->attributes();
						$relId = isset($attributes['Id']) ? (string)$attributes['Id'] : '';
						$target = isset($attributes['Target']) ? (string)$attributes['Target'] : '';
						if ($relId !== '' && $target !== '') {
							$relMap[$relId] = $target;
						}
					}
				}

				$preferredRelId = '';
				$firstRelId = '';
				$workbookChildren = $workbook->children($mainNs);
				if (isset($workbookChildren->sheets)) {
					foreach ($workbookChildren->sheets->sheet as $sheet) {
						$relAttributes = $sheet->attributes($relNs, true);
						$relId = isset($relAttributes['id']) ? (string)$relAttributes['id'] : '';
						$sheetAttributes = $sheet->attributes();
						$sheetName = isset($sheetAttributes['name']) ? trim((string)$sheetAttributes['name']) : '';
						if ($firstRelId === '' && $relId !== '') {
							$firstRelId = $relId;
						}
						if ($preferredRelId === '' && in_array(strtolower($sheetName), array('dulieuimport', 'sheet1'), true)) {
							$preferredRelId = $relId;
						}
					}
				}

				$targetRelId = $preferredRelId !== '' ? $preferredRelId : $firstRelId;
				if ($targetRelId !== '' && isset($relMap[$targetRelId])) {
					$sheetPath = 'xl/'.ltrim($relMap[$targetRelId], '/');
				}
			}
		}

		if ($sheetPath === '') {
			$sheetPath = 'xl/worksheets/sheet1.xml';
		}

		$sheetXml = $zip->getFromName($sheetPath);
		$zip->close();
		if ($sheetXml === false) {
			return $rows;
		}

		$xml = @simplexml_load_string($sheetXml);
		if (!$xml) {
			return $rows;
		}
		$sheetChildren = $xml->children($mainNs);
		if (!isset($sheetChildren->sheetData) || !isset($sheetChildren->sheetData->row)) {
			return $rows;
		}

		foreach ($sheetChildren->sheetData->row as $row) {
			$current = array();
			foreach ($row->c as $cell) {
				$cellAttributes = $cell->attributes();
				$column = preg_replace('/[0-9]+/', '', (string)$cellAttributes['r']);
				$index = $this->xlsxColumnIndex($column);
				$value = '';
				$cellType = isset($cellAttributes['t']) ? (string)$cellAttributes['t'] : '';
				$cellChildren = $cell->children($mainNs);
				if (isset($cellChildren->v)) {
					$value = (string)$cellChildren->v;
					if ($cellType === 's') {
						$value = isset($sharedStrings[(int)$value]) ? $sharedStrings[(int)$value] : $value;
					}
				} elseif ($cellType === 'inlineStr' && isset($cellChildren->is)) {
					$inlineChildren = $cellChildren->is->children($mainNs);
					if (isset($inlineChildren->t)) {
						$value = (string)$inlineChildren->t;
					}
				}
				$current[$index] = trim((string)$value);
			}

			if (!empty($current)) {
				ksort($current);
				$maxIndex = max(array_keys($current));
				$filled = array();
				for ($i = 0; $i <= $maxIndex; $i++) {
					$filled[] = isset($current[$i]) ? $current[$i] : '';
				}
				$rows[] = $filled;
			}
		}

		return $rows;
	}

	private function xlsxColumnIndex($column)
	{
		$length = strlen($column);
		$index = 0;
		for ($i = 0; $i < $length; $i++) {
			$index = $index * 26 + (ord($column[$i]) - ord('A') + 1);
		}
		return $index - 1;
	}

	private function detectCsvDelimiter($line, $default = ',')
	{
		$candidates = array(',', ';', "\t", '|');
		$best = $default;
		$bestCount = -1;
		foreach ($candidates as $candidate) {
			$count = substr_count($line, $candidate);
			if ($count > $bestCount) {
				$best = $candidate;
				$bestCount = $count;
			}
		}
		return $best;
	}

	private function isBinaryExcelFile($filePath)
	{
		if (!is_readable($filePath)) {
			return false;
		}
		$handle = fopen($filePath, 'rb');
		if ($handle === false) {
			return false;
		}
		$signature = fread($handle, 8);
		fclose($handle);
		return $signature === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
	}

	private function generateStudentPassword($length = 10)
	{
		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$!';
		$password = '';
		$max = strlen($chars) - 1;

		for ($i = 0; $i < $length; $i++) {
			$password .= $chars[random_int(0, $max)];
		}

		return $password;
	}
	private function maskEmail($email)
	{
		if (strpos($email, '@') === false) return $email;
		$parts = explode('@', $email, 2);
		$user = $parts[0];
		$domain = $parts[1];
		$prefix = substr($user, 0, min(2, strlen($user)));
		return $prefix.str_repeat('*', max(1, strlen($user) - strlen($prefix))).'@'.$domain;
	}
	public function insertcompany(){
		global $db;
		$result = array();
		$company_name = isset($_POST['company_name']) ? $db->escapestring(trim($_POST['company_name'])) : '';
		$companyTax = isset($_POST['tax_code']) ? $db->escapestring(trim($_POST['tax_code'])) : '';
		if ($company_name === '' || $companyTax === '') {
			$result['status'] = 400;
			$result['message'] = 'Tên công ty hoặc mã số thuế không được bỏ trống';
			echo json_encode($result);
			return;
		}

		// K	iểm tra mã số thuế đã tồn tại trong bảng hicrm_employers
		$db->query("SELECT id FROM hicrm_employers WHERE tax_code = '".$companyTax."' LIMIT 1");
		if($db->num_row()){
			$result['status'] = 400;
			$result['message'] = 'Mã số thuế đã tồn tại';
			echo json_encode($result);
			return;
		}

		// Chèn chỉ tên và mã số thuế
		$db->query("INSERT INTO hicrm_employers (company_name, tax_code) VALUES ('".$company_name."', '".$companyTax."')");
		$result['status'] = 200;
		$result['message'] = 'Tạo công ty thành công';

		echo json_encode($result);
	}
	public function registeremployer(){
		global $db;
		$result = array();	
		$contact_name = isset($_POST['contact_name']) ? $db->escapestring(trim($_POST['contact_name'])) : '';
		$email = isset($_POST['email']) ? $db->escapestring(trim($_POST['email'])) : '';
		$company_id = isset($_POST['company_id']) ? $db->escapestring(trim($_POST['company_id'])) : '';
		$phone = isset($_POST['phone']) ? $db->escapestring(trim($_POST['phone'])) : '';
		$password = isset($_POST['password']) ? $db->escapestring(trim($_POST['password'])) : '';
		// $confirm_password = isset($_POST['confirm_password']) ? $db->escapestring(trim($_POST['confirm_password'])) : '';
		//check email exist
		// echo $email, $contact_name, $company_id, $phone, $password.'ádasdasd' ;
		$db->query("SELECT id FROM hicrm_users WHERE user_email = '".$email."' LIMIT 1");
		if($db->num_row()){	
			$result['status'] = 400;
			$result['message'] = 'Email đã tồn tại';
			echo json_encode($result);
			return;
		}
		//insert data to users table
		
		$db->query("INSERT INTO hicrm_users (employee_id, full_name, user_email, user_phone, user_password, user_group, user_status, user_created_at) VALUES ('".$company_id."', '".$contact_name."', '".$email."', '".$phone."', '".md5($password)."', '2', '1', '".date('Y-m-d H:i:s')."')");	
		$emailSent = false;
		$emailMessage = '';
		$emailError = '';
		// tạo token xác thực email
		$token     = bin2hex(random_bytes(32));
		//tạo token hết hạn sau 15 phút
		$expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
		
		// update token vào database
		$db->query("UPDATE hicrm_users SET user_email_verify_token = '".$token."', user_email_verified_at = '".$expiresAt."' WHERE user_email = '".$email."'");


		if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$emailSent = $this->mail->sendVerifyEmail($contact_name, $email, $token, 'Xác thực tài khoản đăng nhập hệ thống Cổng thông tin việc làm Trường Cao đẳng Kon Tum');
			$emailError = isset($this->studentMailError) ? $this->studentMailError : '';
			$emailMessage = $emailSent
				? 'Hệ thống đã gửi liên kết xác thực đến email '.$this->maskEmail($email).'.'
				: 'Tài khoản đã được tạo nhưng chưa thể xác thực email. Vui lòng kiểm tra cấu hình liên hệ quản trị viên.';
		} else {
			$emailMessage = 'Chưa có email hợp lệ, vì vậy không thể gửi liên kết xác thực qua email.';
		}
		echo json_encode(array(
			'success' => true,
			'status' => 200,
			'message' => $emailMessage,
			'email' => $email !== '' ? $this->maskEmail($email) : '',
			'email_sent' => $emailSent,
			'mail_error' => $emailError
		));
		return;
	}

	public function registercandidate(){
		global $db;
		$result = array();	
		$fullname = isset($_POST['fullname']) ? $db->escapestring(trim($_POST['fullname'])) : '';
		$email = isset($_POST['email']) ? $db->escapestring(trim($_POST['email'])) : '';
		$phone = isset($_POST['phone']) ? $db->escapestring(trim($_POST['phone'])) : '';
		$password = isset($_POST['password']) ? $db->escapestring(trim($_POST['password'])) : '';
		// $confirm_password = isset($_POST['confirm_password']) ? $db->escapestring(trim($_POST['confirm_password'])) : '';
		//check email exist
		// echo $email, $contact_name, $company_id, $phone, $password.'ádasdasd' ;
		$db->query("SELECT id FROM hicrm_users WHERE user_email = '".$email."' LIMIT 1");
		if($db->num_row()){	
			$result['status'] = 400;
			$result['message'] = 'Email đã tồn tại';
			echo json_encode($result);
			return;
		}
		//insert data to users table
		
		$db->query("INSERT INTO hicrm_users (full_name, user_email, user_phone, user_password, user_group, user_status, user_created_at) VALUES ('".$fullname."', '".$email."', '".$phone."', '".md5($password)."', '4', '1', '".date('Y-m-d H:i:s')."')");	
		$emailSent = false;
		$emailMessage = '';
		$emailError = '';
		// tạo token xác thực email
		$token     = bin2hex(random_bytes(32));
		//tạo token hết hạn sau 15 phút
		$expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
		
		// update token vào database
		$db->query("UPDATE hicrm_users SET user_email_verify_token = '".$token."', user_email_verified_at = '".$expiresAt."' WHERE user_email = '".$email."'");


		if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$emailSent = $this->mail->sendVerifyEmail($fullname, $email, $token, 'Xác thực tài khoản đăng nhập hệ thống Cổng thông tin việc làm Trường Cao đẳng Kon Tum');
			$emailError = isset($this->studentMailError) ? $this->studentMailError : '';
			$emailMessage = $emailSent
				? 'Hệ thống đã gửi liên kết xác thực đến email '.$this->maskEmail($email).'.'
				: 'Tài khoản đã được tạo nhưng chưa thể xác thực email. Vui lòng kiểm tra cấu hình liên hệ quản trị viên.';
		} else {
			$emailMessage = 'Chưa có email hợp lệ, vì vậy không thể gửi liên kết xác thực qua email.';
		}
		echo json_encode(array(
			'success' => true,
			'status' => 200,
			'message' => $emailMessage,
			'email' => $email !== '' ? $this->maskEmail($email) : '',
			'email_sent' => $emailSent,
			'mail_error' => $emailError
		));
		return;
	}

	// private function getStudentPasswordEmailTemplate($name, $username, $password)
	// {
	// 	return 
		
	// 	'<div style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,sans-serif;">
    //     <div style="max-width:620px;margin:30px auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.08);">
            
    //         <div style="background:linear-gradient(135deg,#0d4e96,#1976d2);padding:28px;text-align:center;color:#fff;">
    //             <h2 style="margin:0;font-size:24px;">Cổng thông tin việc làm</h2>
    //             <p style="margin:8px 0 0;font-size:14px;">Thông tin tài khoản đăng nhập</p>
    //         </div>

    //         <div style="padding:32px;color:#333;">
    //             <h3 style="margin-top:0;color:#0d4e96;">Xin chào '.$name.',</h3>

    //             <p style="font-size:15px;line-height:1.6;">
    //                 Tài khoản của bạn đã được tạo thành công. Vui lòng sử dụng thông tin bên dưới để đăng nhập hệ thống.
    //             </p>

    //             <div style="background:#f0f6ff;border:1px solid #d8e8ff;border-radius:12px;padding:20px;margin:24px 0;">
    //                 <p style="margin:0 0 10px ;border-bottom:1px solid #d8e8ff;padding-bottom:10px;"><b>Email:</b> '.$username.'</p>
    //                 <p style="margin:0;"><b>Mật khẩu:</b> 
    //                     <span style="font-size:18px;color:#d35400;font-weight:bold;">'.$password.'</span>
    //                 </p>
    //             </div>
    //             <p style="color:#b42318"><strong>Lưu ý:</strong> Vui lòng đổi mật khẩu sau khi đăng nhập lần đầu.</p>
    //             <div style="text-align:center;margin:30px 0;">
    //                 <a href="'.XC_URL.'"
    //                    style="background:#0d4e96;color:#fff;text-decoration:none;padding:14px 28px;border-radius:30px;font-weight:bold;display:inline-block;">
    //                     Đăng nhập ngay
    //                 </a>
    //             </div>

    //             <p style="font-size:14px;color:#777;">
    //               Email này được gửi tự động, vui lòng không trả lời.
    //             </p>
    //         </div>

    //         <div style="background:#f1f3f6;padding:18px;text-align:center;font-size:13px;color:#777;">
    //             © '.date('Y').' Cổng thông tin việc làm. All rights reserved.
    //         </div>
    //     </div>
    // </div>';
	// }
//End API student register
// end code Cương

	// Thêm nhóm quyền mới
	public function addGroup(){
		global $db;
		$this->ensureAdminPermissionTables();
		if(!$this->requireAdminApiPermission('groups')){ return; }
		$group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
		$group_name = $db->escapestring(trim($_POST['group_name'] ?? ''));
		$group_class = $db->escapestring($_POST['group_class'] ?? '');
		$group_icon = $db->escapestring($_POST['group_icon'] ?? '');
		$permission_ids = isset($_POST['permission_ids']) && is_array($_POST['permission_ids']) ? $_POST['permission_ids'] : array();
		$result = array();

		if(empty($group_name)){
			$result['status'] = 400;
			$result['message'] = 'Tên nhóm quyền không được để trống';
			echo json_encode($result);
			return;
		}
		if($group_id === 1){
			$result['status'] = 403;
			$result['message'] = 'Không thể thay đổi nhóm Super Admin.';
			echo json_encode($result);
			return;
		}
		if(in_array($group_id, array(2, 3, 4), true)){
			$result['status'] = 403;
			$result['message'] = 'Đây là nhóm tài khoản ngoài trang chủ, không thể cấp quyền Admin trực tiếp.';
			echo json_encode($result);
			return;
		}
		if($group_id > 0){
			$db->query("SELECT user_group FROM hicrm_users WHERE id = '".intval($_SESSION['user']['id'])."' LIMIT 1");
			$current_admin = $db->fetch_object(true);
			if($current_admin && intval($current_admin->user_group) === $group_id){
				$result['status'] = 403;
				$result['message'] = 'Không thể chỉnh sửa nhóm quyền của chính tài khoản đang đăng nhập.';
				echo json_encode($result);
				return;
			}
			$db->query("SELECT id FROM hicrm_user_groups WHERE id = '".$group_id."' AND group_status NOT IN(99) LIMIT 1");
			if(!$db->num_row()){
				$result['status'] = 404;
				$result['message'] = 'Nhóm quyền không tồn tại.';
				echo json_encode($result);
				return;
			}
		}

		// Kiểm tra nhóm quyền đã tồn tại chưa
		$db->query("SELECT id FROM hicrm_user_groups WHERE group_name = '".$group_name."' AND group_status != 99".($group_id > 0 ? " AND id <> '".$group_id."'" : ""));
		if($db->num_row()){
			$result['status'] = 400;
			$result['message'] = 'Tên nhóm quyền đã tồn tại';
			echo json_encode($result);
			return;
		}

		$valid_permission_ids = array();
		foreach($permission_ids as $permission_id){
			$permission_id = intval($permission_id);
			if($permission_id > 0){
				$valid_permission_ids[] = $permission_id;
			}
		}
		$valid_permission_ids = array_values(array_unique($valid_permission_ids));
		if(!empty($valid_permission_ids)){
			$db->query("SELECT id FROM hicrm_admin_menu_permissions WHERE permission_status = 1 AND id IN (".implode(',', $valid_permission_ids).")");
			$rows = $db->fetch_object();
			$valid_permission_ids = array();
			if(is_array($rows)){ foreach($rows as $row){ $valid_permission_ids[] = intval($row->id); } }
		}

		$db->query("START TRANSACTION");
		if($group_id > 0){
			$db->query("UPDATE hicrm_user_groups SET group_name = '".$group_name."', group_class = '".$group_class."', group_icon = '".$group_icon."' WHERE id = '".$group_id."' LIMIT 1");
		}else{
			$db->query("INSERT INTO hicrm_user_groups(group_name, group_class, group_icon, group_status) VALUES ('".$group_name."','".$group_class."','".$group_icon."',1)");
			$db->query("SELECT LAST_INSERT_ID() AS id");
			$inserted = $db->fetch_object(true);
			$group_id = $inserted ? intval($inserted->id) : 0;
		}

		$db->query("DELETE FROM hicrm_user_group_permissions WHERE group_id = '".$group_id."'");
		foreach($valid_permission_ids as $permission_id){
			$db->query("INSERT IGNORE INTO hicrm_user_group_permissions(group_id, permission_id) VALUES ('".$group_id."','".$permission_id."')");
		}
		$db->query("COMMIT");

		$result['status'] = 200;
		$result['message'] = (isset($_POST['method']) && $_POST['method'] === 'edit') ? 'Cập nhật nhóm quyền thành công' : 'Thêm nhóm quyền thành công';
		$result['url'] = XC_URL."/admin/groups";

		echo json_encode($result);
	}

	public function assignAdminGroup()
	{
		global $db;
		if(!$this->requireAdminApiPermission('users')){ return; }
		$result = array();
		$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
		$user_group = isset($_POST['user_group']) ? intval($_POST['user_group']) : 0;

		if($user_id <= 0){
			$result['status'] = 400;
			$result['message'] = 'Tài khoản admin không hợp lệ';
			echo json_encode($result);
			return;
		}

		if($user_group <= 0){
			$result['status'] = 400;
			$result['message'] = 'Vui lòng chọn nhóm quyền';
			echo json_encode($result);
			return;
		}
		if($user_id === intval($_SESSION['user']['id'])){
			$result['status'] = 403;
			$result['message'] = 'Không thể tự thay đổi nhóm quyền của tài khoản đang đăng nhập.';
			echo json_encode($result);
			return;
		}
		if($user_group === 1 && !$this->currentAdminIsSuperAdmin()){
			$result['status'] = 403;
			$result['message'] = 'Chỉ Super Admin mới được gán nhóm Super Admin.';
			echo json_encode($result);
			return;
		}

		$db->query("SELECT id FROM hicrm_user_groups WHERE id = '".$user_group."' AND group_status NOT IN(99) LIMIT 1");
		if(!$db->num_row()){
			$result['status'] = 404;
			$result['message'] = 'Nhóm quyền không tồn tại';
			echo json_encode($result);
			return;
		}
		if(!$this->adminAccountHasAccess($user_group)){
			$result['status'] = 400;
			$result['message'] = 'Nhóm được chọn chưa có quyền truy cập Admin.';
			echo json_encode($result);
			return;
		}

		$db->query("SELECT u.id, u.user_group
			FROM hicrm_users u
			INNER JOIN hicrm_user_groups g ON u.user_group = g.id AND g.group_status NOT IN(99)
			WHERE u.id = '".$user_id."' AND u.user_status NOT IN(99)
			LIMIT 1");
		if(!$db->num_row()){
			$result['status'] = 404;
			$result['message'] = 'Không tìm thấy tài khoản admin cần phân quyền';
			echo json_encode($result);
			return;
		}
		$target_user = $db->fetch_object(true);
		if($target_user && intval($target_user->user_group) === 1 && !$this->currentAdminIsSuperAdmin()){
			$result['status'] = 403;
			$result['message'] = 'Không thể thay đổi nhóm của tài khoản Super Admin.';
			echo json_encode($result);
			return;
		}

		$db->query("UPDATE hicrm_users SET user_group = '".$user_group."' WHERE id = '".$user_id."' LIMIT 1");
		$result['status'] = 200;
		$result['message'] = 'Cập nhật nhóm quyền thành công';
		$result['url'] = XC_URL.'/admin/users';
		echo json_encode($result);
	}
	// End thêm nhóm quyền mới

	public function linkemployer(){
		global $db;
		if(!$this->requireAdminApiPermission('employers', false)){ return; }
		$result = array();
		$id = isset($_POST['id']) ? $db->escapestring($_POST['id']) : '';

		if($id == ''){
			$result['status'] = 400;
			$result['message'] = 'ID nhà tuyển dụng không hợp lệ';
			echo json_encode($result);
			return;
		}

		$db->query("SELECT id FROM hicrm_employers WHERE id = '".$id."'");
		if(!$db->num_row()){
			$result['status'] = 404;
			$result['message'] = 'Nhà tuyển dụng không tồn tại';
			echo json_encode($result);
			return;
		}

		$db->query("UPDATE hicrm_employers SET is_linked_school = 1 WHERE id = '".$id."'");
		$result['status'] = 200;
		$result['message'] = 'Liên kết nhà tuyển dụng thành công';
		echo json_encode($result);
	}

	public function deleteemployer(){
		global $db;
		if(!$this->requireAdminApiPermission('employers', false)){ return; }
		$result = array();
		$id = isset($_POST['id']) ? $db->escapestring($_POST['id']) : '';

		if($id == ''){
			$result['status'] = 400;
			$result['message'] = 'ID nhà tuyển dụng không hợp lệ';
			echo json_encode($result);
			return;
		}

		$db->query("SELECT id FROM hicrm_employers WHERE id = '".$id."'");
		if(!$db->num_row()){
			$result['status'] = 404;
			$result['message'] = 'Nhà tuyển dụng không tồn tại';
			echo json_encode($result);
			return;
		}

		$db->query("UPDATE hicrm_users SET employee_id = 0 WHERE employee_id = '".$id."' AND user_group = '2'");
		$db->query("DELETE FROM hicrm_employers WHERE id = '".$id."'");

		$result['status'] = 200;
		$result['message'] = 'Xóa nhà tuyển dụng thành công';
		echo json_encode($result);
	}

	private function employerDashboardApiResponse($result){
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($result);
	}

	private function employerDashboardApiContext(){
		global $db;
		$user = null;
		$employer = null;

		if(isset($_SESSION['user']['id']) && $_SESSION['user']['id'] != ""){
			$uid = $db->escapestring($_SESSION['user']['id']);
			$db->query("SELECT * FROM hicrm_users WHERE id = '".$uid."' AND user_group = '2' LIMIT 1");
			if($db->num_row() > 0){
				$user = $db->fetch_object(true);
			}
		}

		if(!$user){
			$db->query("SELECT * FROM hicrm_users WHERE user_group = '2' ORDER BY employee_id DESC, id ASC LIMIT 1");
			if($db->num_row() > 0){
				$user = $db->fetch_object(true);
			}
		}

		if($user && intval($user->employee_id) > 0){
			$db->query("SELECT * FROM hicrm_employers WHERE id = '".intval($user->employee_id)."' LIMIT 1");
			if($db->num_row() > 0){
				$employer = $db->fetch_object(true);
			}
		}

		if(!$employer){
			$db->query("SELECT * FROM hicrm_employers ORDER BY id ASC LIMIT 1");
			if($db->num_row() > 0){
				$employer = $db->fetch_object(true);
			}
		}

		return array('user' => $user, 'employer' => $employer);
	}

	private function employerDashboardApiTableExists($table_name){
		global $db;
		$table_name = $db->escapestring($table_name);
		$db->query("SHOW TABLES LIKE '".$table_name."'");
		return $db->num_row() > 0;
	}

	private function employerDashboardUploadImage($field_name, $max_size = 4194304){
		if(!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] == 4){
			return array('status' => 204, 'path' => '');
		}

		if($_FILES[$field_name]['error'] != 0){
			return array('status' => 400, 'message' => 'File tải lên không hợp lệ');
		}

		if($_FILES[$field_name]['size'] > $max_size){
			return array('status' => 400, 'message' => 'Dung lượng ảnh vượt quá giới hạn cho phép');
		}

		$file_ext = strtolower(pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION));
		$allowed = array('jpg', 'jpeg', 'png', 'webp', 'gif');
		if(!in_array($file_ext, $allowed)){
			return array('status' => 400, 'message' => 'Chỉ cho phép upload ảnh JPG, PNG, WEBP hoặc GIF');
		}

		if(!@getimagesize($_FILES[$field_name]['tmp_name'])){
			return array('status' => 400, 'message' => 'File tải lên không phải hình ảnh hợp lệ');
		}

		$upload_dir = './uploads/employers/';
		if(!is_dir($upload_dir)){
			mkdir($upload_dir, 0777, true);
		}

		$file_name = $field_name.'_'.date('YmdHis').'_'.rand(1000, 9999).'.'.$file_ext;
		$target = $upload_dir.$file_name;
		if(!move_uploaded_file($_FILES[$field_name]['tmp_name'], $target)){
			return array('status' => 500, 'message' => 'Không thể lưu file ảnh');
		}

		return array('status' => 200, 'path' => 'uploads/employers/'.$file_name);
	}

	public function employeraccountupdate(){
		global $db;
		$context = $this->employerDashboardApiContext();
		$user = $context['user'];

		if(!$user){
			$this->employerDashboardApiResponse(array('status' => 404, 'message' => 'Không tìm thấy tài khoản nhà tuyển dụng'));
			return;
		}

		$user_username = isset($_POST['user_username']) ? trim($_POST['user_username']) : '';
		$full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
		$user_email = isset($_POST['user_email']) ? trim($_POST['user_email']) : '';
		$user_phone = isset($_POST['user_phone']) ? trim($_POST['user_phone']) : '';
		$user_is_subscribed = isset($_POST['user_is_subscribed']) && intval($_POST['user_is_subscribed']) == 1 ? 1 : 0;

		if($full_name == ''){
			$this->employerDashboardApiResponse(array('status' => 400, 'message' => 'Vui lòng nhập họ và tên'));
			return;
		}

		if($user_email == '' || !filter_var($user_email, FILTER_VALIDATE_EMAIL)){
			$this->employerDashboardApiResponse(array('status' => 400, 'message' => 'Email không hợp lệ'));
			return;
		}

		$current_user_id = intval($user->id);
		$db->query("SELECT id FROM hicrm_users WHERE user_email = '".$db->escapestring($user_email)."' AND id <> '".$current_user_id."' LIMIT 1");
		if($db->num_row() > 0){
			$this->employerDashboardApiResponse(array('status' => 409, 'message' => 'Email này đã được sử dụng bởi tài khoản khác'));
			return;
		}

		if($user_username != ''){
			$db->query("SELECT id FROM hicrm_users WHERE user_username = '".$db->escapestring($user_username)."' AND id <> '".$current_user_id."' LIMIT 1");
			if($db->num_row() > 0){
				$this->employerDashboardApiResponse(array('status' => 409, 'message' => 'Tên đăng nhập này đã được sử dụng'));
				return;
			}
		}

		$fields = array(
			"user_username = '".$db->escapestring($user_username)."'",
			"full_name = '".$db->escapestring($full_name)."'",
			"user_email = '".$db->escapestring($user_email)."'",
			"user_phone = '".$db->escapestring($user_phone)."'",
			"user_is_subscribed = '".$user_is_subscribed."'",
			"user_updated_at = NOW()"
		);

		$db->query("UPDATE hicrm_users SET ".implode(',', $fields)." WHERE id = '".$current_user_id."' LIMIT 1");

		if(isset($_SESSION['user']['id']) && intval($_SESSION['user']['id']) == $current_user_id){
			$_SESSION['user']['email'] = $user_email;
			$_SESSION['user']['full_name'] = $full_name;
			$_SESSION['user']['user_username'] = $user_username;
		}

		$this->employerDashboardApiResponse(array('status' => 200, 'message' => 'Cập nhật tài khoản thành công'));
	}

	public function employercompanyupdate(){
		global $db;
		$context = $this->employerDashboardApiContext();
		$employer = $context['employer'];

		if(!$employer){
			$this->employerDashboardApiResponse(array('status' => 404, 'message' => 'Không tìm thấy nhà tuyển dụng'));
			return;
		}

		$company_name = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';
		if($company_name == ''){
			$this->employerDashboardApiResponse(array('status' => 400, 'message' => 'Vui lòng nhập tên công ty'));
			return;
		}

		$fields = array(
			"company_name = '".$db->escapestring($company_name)."'",
			"tax_code = '".$db->escapestring(isset($_POST['tax_code']) ? $_POST['tax_code'] : '')."'",
			"job_category_id = ".(isset($_POST['job_category_id']) && $_POST['job_category_id'] !== '' ? "'".intval($_POST['job_category_id'])."'" : "NULL"),
			"company_size = '".$db->escapestring(isset($_POST['company_size']) ? $_POST['company_size'] : '')."'",
			"address_detail = '".$db->escapestring(isset($_POST['address_detail']) ? $_POST['address_detail'] : '')."'",
			"website_url = '".$db->escapestring(isset($_POST['website_url']) ? $_POST['website_url'] : '')."'",
			"fanpage_url = '".$db->escapestring(isset($_POST['fanpage_url']) ? $_POST['fanpage_url'] : '')."'",
			"description = '".$db->escapestring(isset($_POST['description']) ? $_POST['description'] : '')."'",
			"updated_at = NOW()"
		);

		$db->query("UPDATE hicrm_employers SET ".implode(',', $fields)." WHERE id = '".intval($employer->id)."' LIMIT 1");
		$this->employerDashboardApiResponse(array('status' => 200, 'message' => 'Cập nhật thông tin công ty thành công'));
	}

	public function employerimagesupdate(){
		global $db;
		$context = $this->employerDashboardApiContext();
		$employer = $context['employer'];

		if(!$employer){
			$this->employerDashboardApiResponse(array('status' => 404, 'message' => 'Không tìm thấy nhà tuyển dụng'));
			return;
		}

		$fields = array();
		$logo = $this->employerDashboardUploadImage('logo_file', 2097152);
		if($logo['status'] == 200){
			$fields[] = "logo_url = '".$db->escapestring($logo['path'])."'";
		}elseif($logo['status'] != 204){
			$this->employerDashboardApiResponse($logo);
			return;
		}

		$cover = $this->employerDashboardUploadImage('cover_file', 4194304);
		if($cover['status'] == 200){
			$fields[] = "cover_url = '".$db->escapestring($cover['path'])."'";
		}elseif($cover['status'] != 204){
			$this->employerDashboardApiResponse($cover);
			return;
		}

		if(count($fields) == 0){
			$this->employerDashboardApiResponse(array('status' => 400, 'message' => 'Vui lòng chọn logo hoặc ảnh bìa để upload'));
			return;
		}

		$fields[] = "updated_at = NOW()";
		$db->query("UPDATE hicrm_employers SET ".implode(',', $fields)." WHERE id = '".intval($employer->id)."' LIMIT 1");
		$this->employerDashboardApiResponse(array('status' => 200, 'message' => 'Upload hình ảnh thành công'));
	}

	public function employerjobsave(){
		global $db;
		$context = $this->employerDashboardApiContext();
		$employer = $context['employer'];

		if(!$employer){
			$this->employerDashboardApiResponse(array('status' => 404, 'message' => 'Không tìm thấy nhà tuyển dụng'));
			return;
		}

		$title = isset($_POST['title']) ? trim($_POST['title']) : '';
		$job_description = isset($_POST['job_description']) ? trim($_POST['job_description']) : '';
		$deadline = isset($_POST['deadline']) ? trim($_POST['deadline']) : '';
		if($title == '' || $job_description == '' || $deadline == ''){
			$this->employerDashboardApiResponse(array('status' => 400, 'message' => 'Vui lòng nhập tên vị trí, mô tả công việc và hạn nộp hồ sơ'));
			return;
		}

		$job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
		$status = isset($_POST['status']) && $_POST['status'] != '' ? $_POST['status'] : 'pending';
		$allowed_status = array('draft', 'pending', 'published', 'closed', 'rejected');
		if(!in_array($status, $allowed_status)){
			$status = 'pending';
		}

		$data = array(
			"employer_id = '".intval($employer->id)."'",
			"job_category_id = ".(isset($_POST['job_category_id']) && $_POST['job_category_id'] !== '' ? "'".intval($_POST['job_category_id'])."'" : "NULL"),
			"province_id = ".(isset($_POST['province_id']) && $_POST['province_id'] !== '' ? "'".intval($_POST['province_id'])."'" : "NULL"),
			"title = '".$db->escapestring($title)."'",
			"quantity = '".(isset($_POST['quantity']) ? intval($_POST['quantity']) : 1)."'",
			"job_description = '".$db->escapestring($job_description)."'",
			"experience_years = '".(isset($_POST['experience_years']) ? intval($_POST['experience_years']) : 0)."'",
			"degree_required = '".$db->escapestring(isset($_POST['degree_required']) ? $_POST['degree_required'] : '')."'",
			"professional_skills = '".$db->escapestring(isset($_POST['professional_skills']) ? $_POST['professional_skills'] : '')."'",
			"soft_skills = '".$db->escapestring(isset($_POST['soft_skills']) ? $_POST['soft_skills'] : '')."'",
			"other_requirements = '".$db->escapestring(isset($_POST['other_requirements']) ? $_POST['other_requirements'] : '')."'",
			"salary_id = ".(isset($_POST['salary_id']) && $_POST['salary_id'] !== '' ? "'".intval($_POST['salary_id'])."'" : "NULL"),
			"benefits_description = '".$db->escapestring(isset($_POST['benefits_description']) ? $_POST['benefits_description'] : '')."'",
			"rewards_description = '".$db->escapestring(isset($_POST['rewards_description']) ? $_POST['rewards_description'] : '')."'",
			"work_environment = '".$db->escapestring(isset($_POST['work_environment']) ? $_POST['work_environment'] : '')."'",
			"work_type = '".$db->escapestring(isset($_POST['work_type']) ? $_POST['work_type'] : '')."'",
			"address_detail = '".$db->escapestring(isset($_POST['address_detail']) ? $_POST['address_detail'] : '')."'",
			"working_time = '".$db->escapestring(isset($_POST['working_time']) ? $_POST['working_time'] : '')."'",
			"deadline = '".$db->escapestring($deadline)."'",
			"status = '".$db->escapestring($status)."'",
			"updated_at = NOW()"
		);

		if($job_id > 0){
			$db->query("SELECT id FROM hicrm_job_posts WHERE id = '".$job_id."' AND employer_id = '".intval($employer->id)."' LIMIT 1");
			if(!$db->num_row()){
				$this->employerDashboardApiResponse(array('status' => 404, 'message' => 'Bài đăng không tồn tại'));
				return;
			}
			$db->query("UPDATE hicrm_job_posts SET ".implode(',', $data)." WHERE id = '".$job_id."' LIMIT 1");
			$message = 'Cập nhật bài đăng thành công';
		}else{
			$insert_columns = array('employer_id','job_category_id','province_id','title','quantity','job_description','experience_years','degree_required','professional_skills','soft_skills','other_requirements','salary_id','benefits_description','rewards_description','work_environment','work_type','address_detail','working_time','deadline','status','created_at','updated_at');
			$insert_values = array(
				"'".intval($employer->id)."'",
				(isset($_POST['job_category_id']) && $_POST['job_category_id'] !== '' ? "'".intval($_POST['job_category_id'])."'" : "NULL"),
				(isset($_POST['province_id']) && $_POST['province_id'] !== '' ? "'".intval($_POST['province_id'])."'" : "NULL"),
				"'".$db->escapestring($title)."'",
				"'".(isset($_POST['quantity']) ? intval($_POST['quantity']) : 1)."'",
				"'".$db->escapestring($job_description)."'",
				"'".(isset($_POST['experience_years']) ? intval($_POST['experience_years']) : 0)."'",
				"'".$db->escapestring(isset($_POST['degree_required']) ? $_POST['degree_required'] : '')."'",
				"'".$db->escapestring(isset($_POST['professional_skills']) ? $_POST['professional_skills'] : '')."'",
				"'".$db->escapestring(isset($_POST['soft_skills']) ? $_POST['soft_skills'] : '')."'",
				"'".$db->escapestring(isset($_POST['other_requirements']) ? $_POST['other_requirements'] : '')."'",
				(isset($_POST['salary_id']) && $_POST['salary_id'] !== '' ? "'".intval($_POST['salary_id'])."'" : "NULL"),
				"'".$db->escapestring(isset($_POST['benefits_description']) ? $_POST['benefits_description'] : '')."'",
				"'".$db->escapestring(isset($_POST['rewards_description']) ? $_POST['rewards_description'] : '')."'",
				"'".$db->escapestring(isset($_POST['work_environment']) ? $_POST['work_environment'] : '')."'",
				"'".$db->escapestring(isset($_POST['work_type']) ? $_POST['work_type'] : '')."'",
				"'".$db->escapestring(isset($_POST['address_detail']) ? $_POST['address_detail'] : '')."'",
				"'".$db->escapestring(isset($_POST['working_time']) ? $_POST['working_time'] : '')."'",
				"'".$db->escapestring($deadline)."'",
				"'".$db->escapestring($status)."'",
				"NOW()",
				"NOW()"
			);
			$db->query("INSERT INTO hicrm_job_posts (".implode(',', $insert_columns).") VALUES (".implode(',', $insert_values).")");
			$message = 'Đăng tin tuyển dụng thành công';
		}

		$this->employerDashboardApiResponse(array('status' => 200, 'message' => $message));
	}

	public function employerjobdelete(){
		global $db;
		$context = $this->employerDashboardApiContext();
		$employer = $context['employer'];
		$job_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

		if(!$employer || $job_id <= 0){
			$this->employerDashboardApiResponse(array('status' => 400, 'message' => 'Dữ liệu xóa bài đăng không hợp lệ'));
			return;
		}

		$db->query("UPDATE hicrm_job_posts SET status = 99 WHERE id = '".$job_id."' LIMIT 1");
		$this->employerDashboardApiResponse(array('status' => 200, 'message' => 'Xóa bài đăng thành công'));
	}

	public function employerstudents(){
		global $db;
		$keyword = isset($_GET['keyword']) ? $db->escapestring($_GET['keyword']) : '';
		$where = array("1=1");
		if($keyword != ''){
			$where[] = "(s.student_name LIKE '%".$keyword."%' OR s.student_code LIKE '%".$keyword."%' OR s.student_email LIKE '%".$keyword."%' OR s.student_phone LIKE '%".$keyword."%')";
		}

		$db->query("SELECT s.*, c.job_category_name FROM hicrm_student_profile s LEFT JOIN hicrm_job_categories c ON s.student_major_id = c.id WHERE ".implode(' AND ', $where)." ORDER BY s.student_gpa DESC, s.id DESC LIMIT 60");
		$this->employerDashboardApiResponse(array('status' => 200, 'data' => $db->fetch_object()));
	}

	public function employerapplicants(){
		global $db;
		$keyword = isset($_GET['keyword']) ? $db->escapestring($_GET['keyword']) : '';
		$where = array("1=1");

		if($this->employerDashboardApiTableExists('hicrm_candidates')){
			if($keyword != ''){
				$where[] = "(ca.full_name LIKE '%".$keyword."%' OR ca.desired_position LIKE '%".$keyword."%' OR u.user_email LIKE '%".$keyword."%' OR u.user_phone LIKE '%".$keyword."%')";
			}
			$db->query("SELECT ca.*, u.user_email, u.user_phone, jc.job_category_name FROM hicrm_candidates ca LEFT JOIN hicrm_users u ON ca.user_id = u.id LEFT JOIN hicrm_job_categories jc ON ca.major = jc.id WHERE ".implode(' AND ', $where)." ORDER BY ca.updated_at DESC, ca.id DESC LIMIT 60");
		}else{
			if($keyword != ''){
				$where[] = "(full_name LIKE '%".$keyword."%' OR user_email LIKE '%".$keyword."%' OR user_phone LIKE '%".$keyword."%')";
			}
			$db->query("SELECT id, full_name, user_email, user_phone, user_created_at AS updated_at FROM hicrm_users WHERE user_group = '4' AND ".implode(' AND ', $where)." ORDER BY id DESC LIMIT 60");
		}
		$this->employerDashboardApiResponse(array('status' => 200, 'data' => $db->fetch_object()));
	}

	private function candidateProfileApiResponse($payload){
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($payload);
	}

	private function candidateProfileUpload($fieldName, $candidateId, $kind, $maxSize, $extensions, $imageOnly = false){
		if(!isset($_FILES[$fieldName]) || intval($_FILES[$fieldName]['error']) === UPLOAD_ERR_NO_FILE){
			return array('status' => 204, 'path' => '');
		}
		if(intval($_FILES[$fieldName]['error']) !== UPLOAD_ERR_OK){
			return array('status' => 400, 'message' => 'Tệp tải lên không hợp lệ.');
		}
		if(intval($_FILES[$fieldName]['size']) <= 0 || intval($_FILES[$fieldName]['size']) > $maxSize){
			return array('status' => 400, 'message' => 'Dung lượng tệp tải lên vượt quá giới hạn cho phép.');
		}
		$extension = strtolower(pathinfo((string)$_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
		if(!in_array($extension, $extensions, true)){
			return array('status' => 400, 'message' => 'Định dạng tệp tải lên không được hỗ trợ.');
		}
		if($imageOnly && !@getimagesize($_FILES[$fieldName]['tmp_name'])){
			return array('status' => 400, 'message' => 'Ảnh đại diện không phải là hình ảnh hợp lệ.');
		}

		$uploadDir = './uploads/candidate-profiles/';
		if(!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)){
			return array('status' => 500, 'message' => 'Không thể tạo thư mục lưu tệp.');
		}
		try {
			$token = bin2hex(random_bytes(12));
		} catch (Exception $e) {
			$token = md5(uniqid((string)mt_rand(), true));
		}
		$fileName = $kind.'_'.intval($candidateId).'_'.date('YmdHis').'_'.$token.'.'.$extension;
		if(!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $uploadDir.$fileName)){
			return array('status' => 500, 'message' => 'Không thể lưu tệp tải lên.');
		}
		return array('status' => 200, 'path' => 'uploads/candidate-profiles/'.$fileName);
	}

	public function candidateProfileSave(){
		global $db;
		if($_SERVER['REQUEST_METHOD'] !== 'POST'){
			$this->candidateProfileApiResponse(array('status' => 405, 'message' => 'Phương thức gửi dữ liệu không hợp lệ.'));
			return;
		}
		$userId = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
		if($userId <= 0){
			$this->candidateProfileApiResponse(array('status' => 401, 'message' => 'Vui lòng đăng nhập để lưu hồ sơ.'));
			return;
		}
		$db->query("SELECT * FROM hicrm_users WHERE id = '".$userId."' AND user_status = 1 LIMIT 1");
		if($db->num_row() <= 0){
			$this->candidateProfileApiResponse(array('status' => 401, 'message' => 'Tài khoản không hợp lệ hoặc đã bị khóa.'));
			return;
		}
		$user = $db->fetch_object(true);

		$input = array();
		foreach(array('full_name','email','phone','date_of_birth','gender','province_id','address_detail','degree','major','graduation_year','school_name','soft_skills','career_goal_short','career_goal_long','desired_position','desired_salary','desired_province_id','desired_work_type') as $field){
			$input[$field] = isset($_POST[$field]) ? trim((string)$_POST[$field]) : '';
		}
		$required = array('full_name','email','phone','date_of_birth','gender','province_id','address_detail','degree','major','graduation_year','school_name','soft_skills','career_goal_short','career_goal_long','desired_position','desired_salary','desired_province_id','desired_work_type');
		foreach($required as $field){
			if($input[$field] === ''){
				$this->candidateProfileApiResponse(array('status' => 400, 'message' => 'Vui lòng điền đầy đủ các trường bắt buộc.'));
				return;
			}
		}
		if(!filter_var($input['email'], FILTER_VALIDATE_EMAIL)){
			$this->candidateProfileApiResponse(array('status' => 400, 'message' => 'Email không hợp lệ.'));
			return;
		}
		$date = DateTime::createFromFormat('Y-m-d', $input['date_of_birth']);
		if(!$date || $date->format('Y-m-d') !== $input['date_of_birth'] || $date > new DateTime('today')){
			$this->candidateProfileApiResponse(array('status' => 400, 'message' => 'Ngày sinh không hợp lệ.'));
			return;
		}
		if(!in_array($input['gender'], array('male','female','other'), true) || !in_array($input['degree'], array('high_school','intermediate','college','university','postgraduate','other'), true) || !in_array($input['desired_work_type'], array('full_time','part_time','remote','hybrid','any'), true)){
			$this->candidateProfileApiResponse(array('status' => 400, 'message' => 'Dữ liệu hồ sơ không hợp lệ.'));
			return;
		}
		$year = intval($input['graduation_year']);
		if($year < 1950 || $year > 2100 || intval($input['province_id']) <= 0 || intval($input['major']) <= 0 || intval($input['desired_salary']) <= 0 || intval($input['desired_province_id']) <= 0){
			$this->candidateProfileApiResponse(array('status' => 400, 'message' => 'Vui lòng chọn đầy đủ thông tin danh mục.'));
			return;
		}
		$db->query("SELECT id FROM hicrm_users WHERE user_email = '".$db->escapestring($input['email'])."' AND id <> '".$userId."' LIMIT 1");
		if($db->num_row() > 0){
			$this->candidateProfileApiResponse(array('status' => 409, 'message' => 'Email này đã được sử dụng bởi tài khoản khác.'));
			return;
		}
		$db->query("SELECT * FROM hicrm_candidates WHERE user_id = '".$userId."' LIMIT 1");
		$candidate = $db->num_row() > 0 ? $db->fetch_object(true) : null;
		if(!$candidate){
			$db->query("INSERT INTO hicrm_candidates (user_id, full_name, phone, status, profile_completeness, created_at, updated_at) VALUES ('".$userId."', '".$db->escapestring($input['full_name'])."', '".$db->escapestring($input['phone'])."', 0, 0, NOW(), NOW())");
			$db->query("SELECT * FROM hicrm_candidates WHERE user_id = '".$userId."' LIMIT 1");
			$candidate = $db->fetch_object(true);
		}
		$candidateId = intval($candidate->id);

		$avatar = $this->candidateProfileUpload('avatar_file', $candidateId, 'avatar', 2097152, array('jpg','jpeg','png','webp'), true);
		if($avatar['status'] !== 200 && $avatar['status'] !== 204){ $this->candidateProfileApiResponse($avatar); return; }
		$cv = $this->candidateProfileUpload('cv_file', $candidateId, 'cv', 5242880, array('pdf','doc','docx'));
		if($cv['status'] !== 200 && $cv['status'] !== 204){ $this->candidateProfileApiResponse($cv); return; }
		$avatarUrl = $avatar['status'] === 200 ? $avatar['path'] : trim((string)$candidate->avatar_url);
		$cvUrl = $cv['status'] === 200 ? $cv['path'] : trim((string)$candidate->avatar_url);
		if($avatarUrl === '' || $cvUrl === ''){
			$this->candidateProfileApiResponse(array('status' => 400, 'message' => 'Vui lòng tải lên ảnh đại diện và CV.'));
			return;
		}

		$skills = array_values(array_filter(array_map('trim', explode(',', $input['soft_skills'])), function($skill){ return $skill !== ''; }));
		if(count($skills) === 0){ $this->candidateProfileApiResponse(array('status' => 400, 'message' => 'Vui lòng nhập ít nhất một kỹ năng mềm.')); return; }
		$experienceCompanies = isset($_POST['experience_company']) && is_array($_POST['experience_company']) ? $_POST['experience_company'] : array();
		$experienceRows = array();
		foreach($experienceCompanies as $index => $company){
			$row = array('company' => trim((string)$company), 'position' => trim((string)($_POST['experience_position'][$index] ?? '')), 'start' => trim((string)($_POST['experience_start'][$index] ?? '')), 'end' => trim((string)($_POST['experience_end'][$index] ?? '')), 'description' => trim((string)($_POST['experience_description'][$index] ?? '')));
			if(implode('', $row) === ''){ continue; }
			if($row['company'] === '' || $row['position'] === '' || $row['start'] === ''){ $this->candidateProfileApiResponse(array('status' => 400, 'message' => 'Mỗi kinh nghiệm cần có công ty, vị trí và ngày bắt đầu.')); return; }
			$start = DateTime::createFromFormat('Y-m-d', $row['start']); $end = $row['end'] === '' ? null : DateTime::createFromFormat('Y-m-d', $row['end']);
			if(!$start || $start->format('Y-m-d') !== $row['start'] || ($end && ($end->format('Y-m-d') !== $row['end'] || $end < $start))){ $this->candidateProfileApiResponse(array('status' => 400, 'message' => 'Thời gian kinh nghiệm không hợp lệ.')); return; }
			$experienceRows[] = $row;
		}
		$certificateNames = isset($_POST['certificate_name']) && is_array($_POST['certificate_name']) ? $_POST['certificate_name'] : array();
		$certificateRows = array();
		foreach($certificateNames as $index => $name){
			$row = array('name' => trim((string)$name), 'issuer' => trim((string)($_POST['certificate_issuer'][$index] ?? '')), 'issued' => trim((string)($_POST['certificate_issued_date'][$index] ?? '')), 'expiry' => trim((string)($_POST['certificate_expiry_date'][$index] ?? '')), 'url' => trim((string)($_POST['certificate_url'][$index] ?? '')));
			if(implode('', $row) === ''){ continue; }
			if($row['name'] === ''){ $this->candidateProfileApiResponse(array('status' => 400, 'message' => 'Vui lòng nhập tên chứng chỉ.')); return; }
			foreach(array('issued','expiry') as $dateField){ if($row[$dateField] !== ''){ $parsed = DateTime::createFromFormat('Y-m-d', $row[$dateField]); if(!$parsed || $parsed->format('Y-m-d') !== $row[$dateField]){ $this->candidateProfileApiResponse(array('status' => 400, 'message' => 'Ngày chứng chỉ không hợp lệ.')); return; } } }
			if($row['url'] !== '' && !filter_var($row['url'], FILTER_VALIDATE_URL)){ $this->candidateProfileApiResponse(array('status' => 400, 'message' => 'Liên kết chứng chỉ không hợp lệ.')); return; }
			$certificateRows[] = $row;
		}

		$fields = array("full_name = '".$db->escapestring($input['full_name'])."'", "date_of_birth = '".$db->escapestring($input['date_of_birth'])."'", "gender = '".$db->escapestring($input['gender'])."'", "phone = '".$db->escapestring($input['phone'])."'", "avatar_url = '".$db->escapestring($avatarUrl)."'", "province_id = '".intval($input['province_id'])."'", "address_detail = '".$db->escapestring($input['address_detail'])."'", "degree = '".$db->escapestring($input['degree'])."'", "major = '".intval($input['major'])."'", "graduation_year = '".$year."'", "school_name = '".$db->escapestring($input['school_name'])."'", "soft_skills = '".$db->escapestring(json_encode($skills, JSON_UNESCAPED_UNICODE))."'", "career_goal = '".$db->escapestring($input['career_goal_long'])."'", "career_goal_short = '".$db->escapestring($input['career_goal_short'])."'", "career_goal_long = '".$db->escapestring($input['career_goal_long'])."'", "desired_position = '".$db->escapestring($input['desired_position'])."'", "desired_salary = '".intval($input['desired_salary'])."'", "desired_province_id = '".intval($input['desired_province_id'])."'", "desired_work_type = '".$db->escapestring($input['desired_work_type'])."'", "cv_url = '".$db->escapestring($cvUrl)."'", "is_seeking = '".(isset($_POST['is_seeking']) ? 1 : 0)."'", "profile_completeness = 100", "updated_at = NOW()");
		if($cv['status'] === 200){ $fields[] = 'cv_uploaded_at = NOW()'; }
		$db->query("UPDATE hicrm_candidates SET ".implode(',', $fields)." WHERE id = '".$candidateId."' AND user_id = '".$userId."' LIMIT 1");
		$db->query("UPDATE hicrm_users SET full_name = '".$db->escapestring($input['full_name'])."', user_email = '".$db->escapestring($input['email'])."', user_phone = '".$db->escapestring($input['phone'])."', user_updated_at = NOW() WHERE id = '".$userId."' LIMIT 1");
		$db->query("DELETE FROM hicrm_candidate_experiences WHERE candidate_id = '".$candidateId."'");
		foreach($experienceRows as $row){ $db->query("INSERT INTO hicrm_candidate_experiences (candidate_id, company_name, position, start_date, end_date, description) VALUES ('".$candidateId."', '".$db->escapestring($row['company'])."', '".$db->escapestring($row['position'])."', '".$db->escapestring($row['start'])."', ".($row['end'] === '' ? 'NULL' : "'".$db->escapestring($row['end'])."'").", '".$db->escapestring($row['description'])."')"); }
		$db->query("DELETE FROM hicrm_candidate_certificates WHERE candidate_id = '".$candidateId."'");
		foreach($certificateRows as $row){ $db->query("INSERT INTO hicrm_candidate_certificates (candidate_id, cert_name, issuer, issued_date, expiry_date, cert_url) VALUES ('".$candidateId."', '".$db->escapestring($row['name'])."', '".$db->escapestring($row['issuer'])."', ".($row['issued'] === '' ? 'NULL' : "'".$db->escapestring($row['issued'])."'").", ".($row['expiry'] === '' ? 'NULL' : "'".$db->escapestring($row['expiry'])."'").", '".$db->escapestring($row['url'])."')"); }
		if(isset($_SESSION['user']['id']) && intval($_SESSION['user']['id']) === $userId){ $_SESSION['user']['email'] = $input['email']; $_SESSION['user']['full_name'] = $input['full_name']; }
		$this->candidateProfileApiResponse(array('status' => 200, 'message' => 'Lưu hồ sơ thành công.', 'candidate_id' => $candidateId, 'profile_completeness' => 100));
	}

	// Xóa nhóm quyền
	public function deletegroup(){
		global $db;
		if(!$this->requireAdminApiPermission('groups')){ return; }
		$result = array();
		$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

		if(empty($id)){
			$result['status'] = 400;
			$result['message'] = 'ID nhóm quyền không hợp lệ';
			echo json_encode($result);
			return;
		}
		if($id === 1){
			$result['status'] = 403;
			$result['message'] = 'Không thể xóa nhóm Super Admin.';
			echo json_encode($result);
			return;
		}
		if(in_array($id, array(2, 3, 4), true)){
			$result['status'] = 403;
			$result['message'] = 'Không thể xóa nhóm tài khoản hệ thống ngoài trang chủ.';
			echo json_encode($result);
			return;
		}

		// Kiểm tra nhóm quyền có tồn tại không
		$db->query("SELECT id FROM hicrm_user_groups WHERE id = '".$id."'");
		if(!$db->num_row()){
			$result['status'] = 404;
			$result['message'] = 'Nhóm quyền không tồn tại';
			echo json_encode($result);
			return;
		}
		$db->query("SELECT id FROM hicrm_users WHERE user_group = '".$id."' AND user_status NOT IN(99) LIMIT 1");
		if($db->num_row()){
			$result['status'] = 400;
			$result['message'] = 'Không thể xóa nhóm đang có tài khoản sử dụng.';
			echo json_encode($result);
			return;
		}

		// Xóa nhóm quyền (đánh dấu xóa)
		$db->query("UPDATE hicrm_user_groups SET group_status = 99 WHERE id = '".$id."'");

		$result['status'] = 200;
		$result['message'] = 'Xóa nhóm quyền thành công';

		echo json_encode($result);
	}
	// End xóa nhóm quyền


}













