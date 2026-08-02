<?php

Class indexController Extends baseController
{
	public function index()
    {
		// if(!(isset($_SESSION['user']['id']) && $_SESSION['user']['id'] != "")){ header("Location: ".XC_URL."/admin/login"); }
		
		global $db;
		//  $db->query("SELECT * FROM hicrm_departments WHERE depart_status NOT IN (99)");
		//  $this->view->data['chuyenkhoa'] = $db->fetch_object();
		//  $db->query("SELECT *, e.id as employeeid FROM hicrm_employees as e 
		// LEFT JOIN hicrm_branchs as b ON e.employee_branch = b.id 
		// LEFT JOIN hicrm_departments as d ON e.employee_department = d.id
		// LEFT JOIN hicrm_positions as p ON e.employee_position = p.id
		// WHERE e.employee_status NOT IN (99)");
		// $this->view->data['employee'] = $db->fetch_object();
		// $db->query("SELECT * FROM hicrm_events WHERE event_status NOT IN (99)");
		// $this->view->data['events'] = $db->fetch_object();
		// $db->query("SELECT * FROM hicrm_images WHERE image_status NOT IN (99) AND image_category = '3' ");
		// $this->view->data['slider'] = $db->fetch_object();
		// //không gian phòng khám
		// $db->query("SELECT * FROM hicrm_images WHERE image_status NOT IN (99) AND image_category = '2' ");
		// $this->view->data['pic'] = $db->fetch_object();
		
		$db->query("SELECT COUNT(p.id) AS total
			FROM hicrm_job_posts p
			LEFT JOIN hicrm_employers e ON e.id = p.employer_id
			LEFT JOIN hicrm_job_categories c ON c.id = p.job_category_id
			LEFT JOIN hicrm_provinces pr ON pr.id = p.province_id
			LEFT JOIN hicrm_salary s ON s.id = p.salary_id
			WHERE p.status = 'published'
				AND COALESCE(p.published_at, p.created_at) >= DATE_SUB(NOW(), INTERVAL 75 DAY)");
		$featured_total = $db->fetch_object(true);
		$this->view->data['featured_jobs_total_pages'] = max(1, ceil((int)$featured_total->total / 15));

		$db->query("SELECT p.*, e.company_name, e.logo_url, c.job_category_name, pr.province_name, s.salary_name
			FROM hicrm_job_posts p
			LEFT JOIN hicrm_employers e ON e.id = p.employer_id
			LEFT JOIN hicrm_job_categories c ON c.id = p.job_category_id
			LEFT JOIN hicrm_provinces pr ON pr.id = p.province_id
			LEFT JOIN hicrm_salary s ON s.id = p.salary_id
			WHERE p.status = 'published'
				AND COALESCE(p.published_at, p.created_at) >= DATE_SUB(NOW(), INTERVAL 75 DAY)
			ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.deadline IS NULL ASC, p.deadline ASC, p.id DESC
			LIMIT 15");
		$this->view->data['featured_jobs'] = $db->fetch_object();
		$this->view->data['featured_job_filters'] = $this->buildHomeJobFilters(
			"FROM hicrm_job_posts p
			LEFT JOIN hicrm_employers e ON e.id = p.employer_id
			LEFT JOIN hicrm_job_categories c ON c.id = p.job_category_id
			LEFT JOIN hicrm_provinces pr ON pr.id = p.province_id
			LEFT JOIN hicrm_salary s ON s.id = p.salary_id",
			"WHERE p.status = 'published'
				AND COALESCE(p.published_at, p.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
		);

		$db->query("SELECT COUNT(p.id) AS total
			FROM hicrm_job_posts p
			WHERE p.status = 'published' AND p.province_id IS NOT NULL AND p.province_id <> 0");
		$province_total = $db->fetch_object(true);
		$this->view->data['province_jobs_total_pages'] = max(1, ceil((int)$province_total->total / 6));

		$db->query("SELECT p.*, e.company_name, e.logo_url, c.job_category_name, pr.province_name, s.salary_name
			FROM hicrm_job_posts p
			LEFT JOIN hicrm_employers e ON e.id = p.employer_id
			LEFT JOIN hicrm_job_categories c ON c.id = p.job_category_id
			LEFT JOIN hicrm_provinces pr ON pr.id = p.province_id
			LEFT JOIN hicrm_salary s ON s.id = p.salary_id
			WHERE p.status = 'published' AND p.province_id IS NOT NULL AND p.province_id= 22
			ORDER BY p.created_at DESC, p.deadline IS NULL ASC, p.deadline ASC, p.id DESC
			LIMIT 6");
		$this->view->data['province_jobs'] = $db->fetch_object();

		$db->query("SELECT e.id, e.company_name, e.logo_url, e.created_at, COUNT(p.id) AS published_jobs
			FROM hicrm_employers e
			LEFT JOIN hicrm_job_posts p ON p.employer_id = e.id AND p.status = 'published'
			WHERE e.is_linked_school = 1
			GROUP BY e.id, e.company_name, e.logo_url, e.created_at
			ORDER BY e.created_at DESC, e.id DESC");
		$this->view->data['linked_employers'] = $db->fetch_object();

		$db->query("SELECT e.id, e.company_name, e.logo_url, e.created_at, COUNT(p.id) AS published_jobs
			FROM hicrm_employers e
			LEFT JOIN hicrm_job_posts p ON p.employer_id = e.id AND p.status = 'published'
			GROUP BY e.id, e.company_name, e.logo_url, e.created_at
			ORDER BY e.created_at DESC, e.id DESC");
		$this->view->data['recent_employers'] = $db->fetch_object();

		$db->query("SELECT COUNT(p.id) AS total
			FROM hicrm_job_posts p
			INNER JOIN hicrm_employers e ON e.id = p.employer_id
			WHERE p.status = 'published' AND p.job_post_type IN ('urgent', 'hot') AND e.is_linked_school = 1");
		$urgent_total = $db->fetch_object(true);
		$this->view->data['urgent_jobs_total_pages'] = max(1, ceil((int)$urgent_total->total / 9));

		$db->query("SELECT p.*, e.company_name, e.logo_url, c.job_category_name, pr.province_name, s.salary_name
			FROM hicrm_job_posts p
			INNER JOIN hicrm_employers e ON e.id = p.employer_id
			LEFT JOIN hicrm_job_categories c ON c.id = p.job_category_id
			LEFT JOIN hicrm_provinces pr ON pr.id = p.province_id
			LEFT JOIN hicrm_salary s ON s.id = p.salary_id
			WHERE p.status = 'published' AND p.job_post_type IN ('urgent', 'hot') AND e.is_linked_school = 1
			ORDER BY p.published_at DESC, p.deadline IS NULL ASC, p.deadline ASC, p.created_at DESC, p.id DESC
			LIMIT 9");
		$this->view->data['urgent_jobs'] = $db->fetch_object();
		$this->view->data['urgent_job_filters'] = $this->buildHomeJobFilters(
			"FROM hicrm_job_posts p
			INNER JOIN hicrm_employers e ON e.id = p.employer_id
			LEFT JOIN hicrm_job_categories c ON c.id = p.job_category_id
			LEFT JOIN hicrm_provinces pr ON pr.id = p.province_id
			LEFT JOIN hicrm_salary s ON s.id = p.salary_id",
			"WHERE p.status = 'published' AND p.job_post_type IN ('urgent', 'hot') AND e.is_linked_school = 1"
		);

		$db->query("SELECT id, province_name FROM hicrm_provinces ORDER BY province_name ASC");
		$this->view->data['job_provinces'] = $db->fetch_object();

		$db->query("SELECT c.id, c.job_category_name, c.job_category_icon, COUNT(p.id) AS published_jobs
			FROM hicrm_job_categories c
			LEFT JOIN hicrm_job_posts p ON p.job_category_id = c.id AND p.status = 'published'
			GROUP BY c.id, c.job_category_name, c.job_category_icon
			HAVING COUNT(p.id) > 0
			ORDER BY published_jobs DESC, c.job_category_name ASC
			LIMIT 11");
		$this->view->data['job_categories_with_counts'] = $db->fetch_object();

		$db->query("SELECT COUNT(ca.id) AS total
			FROM hicrm_candidates ca
			LEFT JOIN hicrm_users u ON u.id = ca.user_id
			WHERE ca.status = 3 AND ca.is_seeking = 1 AND (u.id IS NULL OR u.user_status = 1)");
		$cand_total = $db->fetch_object(true);
		$this->view->data['featured_candidates_total_pages'] = max(1, ceil((int)$cand_total->total / 12));

		$db->query("SELECT ca.*, u.user_email, u.user_phone, jc.job_category_name, desired_pr.province_name AS desired_province_name, sal.salary_name
			FROM hicrm_candidates ca
			LEFT JOIN hicrm_users u ON u.id = ca.user_id
			LEFT JOIN hicrm_job_categories jc ON jc.id = ca.major
			LEFT JOIN hicrm_provinces desired_pr ON desired_pr.id = ca.desired_province_id
			LEFT JOIN hicrm_salary sal ON sal.id = ca.desired_salary
			WHERE ca.status = 3 AND ca.is_seeking = 1 AND (u.id IS NULL OR u.user_status = 1)
			ORDER BY ca.updated_at DESC, ca.id DESC
			LIMIT 12");
		$this->view->data['featured_candidates'] = $db->fetch_object();

		$db->query("SELECT *
			FROM hicrm_events
			WHERE event_status = 1
			ORDER BY event_hot DESC, event_created_date DESC, id DESC
			LIMIT 6");
		$this->view->data['home_featured_news'] = $db->fetch_object();

		$db->query("SELECT *
			FROM hicrm_videos
			WHERE video_status = 1
			ORDER BY video_created_at DESC, id DESC");
		$this->view->data['home_videos'] = $db->fetch_object();

		$this->view->show("index");
		
	}
	
	private function countorderbydate($date)
	{
		global $db;
		$db->query("SELECT count(*) as countorder FROM ow_orders WHERE date(order_time) = '".date("Y-m-d",strtotime($date))."'");
		return $db->fetch_object(true)->countorder;
	}
	private function buildHomeJobFilters($fromClause, $whereClause)
	{
		global $db;
		$filters = array(
			'all' => array(
				array('value' => 'all', 'label' => 'Tất cả')
			),
			'location' => array(
				array('value' => 'all', 'label' => 'Tất cả')
			),
			'salary' => array(
				array('value' => 'all', 'label' => 'Tất cả')
			),
			'experience' => array(
				array('value' => 'all', 'label' => 'Tất cả')
			),
			'industry' => array(
				array('value' => 'all', 'label' => 'Tất cả')
			)
		);

		$db->query("SELECT pr.id, pr.province_name, COUNT(p.id) AS total
			".$fromClause."
			".$whereClause." AND p.province_id IS NOT NULL AND p.province_id <> 0
			GROUP BY pr.id, pr.province_name
			ORDER BY total DESC, pr.province_name ASC");
		foreach((array)$db->fetch_object() as $item){
			$filters['location'][] = array(
				'value' => 'loc_'.(int)$item->id,
				'label' => $item->province_name
			);
		}

		$db->query("SELECT s.id, s.salary_name, COUNT(p.id) AS total
			".$fromClause."
			".$whereClause." AND p.salary_id IS NOT NULL AND p.salary_id <> 0
			GROUP BY s.id, s.salary_name
			ORDER BY s.id ASC, total DESC");
		foreach((array)$db->fetch_object() as $item){
			$filters['salary'][] = array(
				'value' => 'sal_'.(int)$item->id,
				'label' => $item->salary_name
			);
		}

		$db->query("SELECT experience_value, COUNT(*) AS total
			FROM (
				SELECT CASE
					WHEN COALESCE(NULLIF(TRIM(p.experience_years), ''), '0') REGEXP '^[0-9]+$'
						THEN CAST(COALESCE(NULLIF(TRIM(p.experience_years), ''), '0') AS UNSIGNED)
					ELSE 0
				END AS experience_value
				".$fromClause."
				".$whereClause."
			) exp
			GROUP BY experience_value
			ORDER BY experience_value ASC");
		foreach((array)$db->fetch_object() as $item){
			$value = (int)$item->experience_value;
			$filters['experience'][] = array(
				'value' => 'exp_'.$value,
				'label' => $value <= 0 ? 'Chưa có kinh nghiệm' : ($value === 1 ? '1 năm' : $value.' năm')
			);
		}

		$db->query("SELECT c.id, c.job_category_name, COUNT(p.id) AS total
			".$fromClause."
			".$whereClause." AND p.job_category_id IS NOT NULL AND p.job_category_id <> 0
			GROUP BY c.id, c.job_category_name
			ORDER BY total DESC, c.job_category_name ASC");
		foreach((array)$db->fetch_object() as $item){
			$filters['industry'][] = array(
				'value' => 'cat_'.(int)$item->id,
				'label' => $item->job_category_name
			);
		}

		return $filters;
	}
	private function countorderbydatedeposited($date)
	{
		global $db;
		$db->query("SELECT count(*) as countorder FROM ow_orders WHERE order_status > 1 AND date(order_time) = '".date("Y-m-d",strtotime($date))."'");
		return $db->fetch_object(true)->countorder;
	}
	
}

?>