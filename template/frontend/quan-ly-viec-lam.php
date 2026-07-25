<?php require "header.php"; ?>
<?php
$jobs = isset($jobs) && is_array($jobs) ? $jobs : array();
$job_categories = isset($job_categories) && is_array($job_categories) ? $job_categories : array();
$job_provinces = isset($job_provinces) && is_array($job_provinces) ? $job_provinces : array();
$salaries = isset($salaries) && is_array($salaries) ? $salaries : array();
$job_filters = isset($job_filters) && is_array($job_filters) ? $job_filters : array();
$page = isset($page) ? (int)$page : 1;
$total_pages = isset($total_pages) ? (int)$total_pages : 1;
$total_jobs = isset($total_jobs) ? (int)$total_jobs : count($jobs);

function jobsPageH($value){
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function jobsPageInitials($name){
  $name = trim((string)$name);
  if($name === ''){ return 'JOB'; }
  $parts = preg_split('/\s+/', $name);
  $letters = '';
  foreach($parts as $part){
    if($part !== ''){ $letters .= mb_substr($part, 0, 1, 'UTF-8'); }
    if(mb_strlen($letters, 'UTF-8') >= 3){ break; }
  }
  return mb_strtoupper($letters ?: mb_substr($name, 0, 3, 'UTF-8'), 'UTF-8');
}

function jobsPageDate($value){
  if(!$value){ return '-'; }
  $time = strtotime($value);
  return $time ? date('d/m/Y', $time) : $value;
}

function jobsPageIsUrgent($job){
  $type = isset($job->job_post_type) ? $job->job_post_type : 'normal';
  return in_array($type, array('urgent', 'hot'), true);
}

function jobsPageTypeLabel($job){
  $type = isset($job->job_post_type) ? $job->job_post_type : 'normal';
  if($type === 'hot'){ return 'Hot'; }
  if($type === 'urgent'){ return 'Tuyển gấp'; }
  return '';
}

function jobsPageUrl($targetPage){
  $params = $_GET;
  // `rt` is added internally by the rewrite rule. Keeping it in the query
  // string overrides the /page/{n} route and always sends the user to page 1.
  unset($params['page'], $params['rt']);
  $url = general::getInstance()->permalink($targetPage, 'manage_jobs_page');
  return $url.(count($params) ? (strpos($url, '?') === false ? '?' : '&').http_build_query($params) : '');
}

function jobsPagePaginationItems($currentPage, $totalPages){
  $currentPage = max(1, (int)$currentPage);
  $totalPages = max(1, (int)$totalPages);
  if($totalPages <= 6){
    return range(1, $totalPages);
  }

  if($currentPage <= 4){
    $pages = range(1, 4);
  }elseif($currentPage >= $totalPages - 3){
    $pages = array(1, '...', $totalPages - 3, $totalPages - 2, $totalPages - 1, $totalPages);
  }else{
    $pages = array(1, '...', $currentPage - 1, $currentPage, $currentPage + 1, '...', $totalPages);
  }
  return $pages;
}
?>

<main class="jobs-manage-page">
  <div class="jobs-manage-inner">
    <form class="job-search-panel" method="get" action="<?php echo XC_URL; ?>/quan-ly-viec-lam.html" aria-label="Tìm kiếm và lọc việc làm">
      <?php if((int)($job_filters['employer_id'] ?? 0) > 0): ?>
        <input type="hidden" name="employer_id" value="<?php echo (int)$job_filters['employer_id']; ?>">
      <?php endif; ?>
      <div class="job-search-main">
        <label class="job-search-field">
          <i class="ti ti-search"></i>
          <input type="text" id="keywordSearch" name="keyword" value="<?php echo jobsPageH($job_filters['keyword'] ?? ''); ?>" placeholder="Nhập từ khóa tìm kiếm ở đây!" autocomplete="off">
        </label>
        <label class="job-search-field">
          <i class="ti ti-map-pin"></i>
          <select id="topLocationFilter" name="province_id" aria-label="Địa điểm">
            <option value="">Địa điểm</option>
            <?php foreach ($job_provinces as $province): ?>
              <option value="<?php echo (int)$province->id; ?>" <?php echo (int)($job_filters['province_id'] ?? 0) === (int)$province->id ? 'selected' : ''; ?>><?php echo jobsPageH($province->province_name); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="job-search-field">
          <i class="ti ti-briefcase"></i>
          <select id="topIndustryFilter" name="job_category_id" aria-label="Ngành nghề">
            <option value="">Ngành nghề</option>
            <?php foreach ($job_categories as $category): ?>
              <option value="<?php echo (int)$category->id; ?>" <?php echo (int)($job_filters['job_category_id'] ?? 0) === (int)$category->id ? 'selected' : ''; ?>><?php echo jobsPageH($category->job_category_name); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
         <label class="job-search-field">
          <select id="topIndustryFilter" name="salary_id" aria-label="Mức lương">
            <option value="">Mức lương</option>
            <?php foreach ($salaries as $salary): ?>
              <option value="<?php echo (int)$salary->id; ?>" <?php echo (int)($job_filters['salary_id'] ?? 0) === (int)$salary->id ? 'selected' : ''; ?>><?php echo jobsPageH($salary->salary_name); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <!-- <button type="submit" class="job-search-submit" id="jobSearchBtn">TÌM KIẾM</button> -->
      </div>

      <div class="job-filter-row">
        <label class="job-filter-select">
          <select id="workTypeFilter" name="work_type" aria-label="Loại hình">
            <option value="">Tất cả loại hình</option>
            <option value="full_time" <?php echo (($job_filters['work_type'] ?? '') === 'full_time') ? 'selected' : ''; ?>>Full-time</option>
            <option value="part_time" <?php echo (($job_filters['work_type'] ?? '') === 'part_time') ? 'selected' : ''; ?>>Part-time</option>
            <option value="remote" <?php echo (($job_filters['work_type'] ?? '') === 'remote') ? 'selected' : ''; ?>>Remote</option>
            <option value="hybrid" <?php echo (($job_filters['work_type'] ?? '') === 'hybrid') ? 'selected' : ''; ?>>Hybrid</option>
          </select>
        </label>
       
        <label class="job-filter-select">
          <select id="postTypeFilter" name="post_type" aria-label="Loại tin">
            <option value="">Tất cả loại tin</option>
            <option value="urgent" <?php echo (($job_filters['post_type'] ?? '') === 'urgent') ? 'selected' : ''; ?>>Tuyển gấp</option>
          </select>
        </label>
        <a class="job-filter-clear" id="jobsFilterReset" href="<?php echo XC_URL; ?>/quan-ly-viec-lam.html">Xóa lọc</a>
      </div>
    </form>

    <div class="jobs-toolbar">
      <div class="jobs-toolbar-title">
        <h1>Danh sách việc làm</h1>
        <p>Các vị trí đang tuyển dụng phù hợp với tiêu chí tìm kiếm.</p>
      </div>
      <div class="jobs-manage-count"><i class="ti ti-briefcase"></i> <span id="jobsVisibleCount"><?php echo $total_jobs; ?></span> việc làm phù hợp</div>
    </div>

    <section class="jobs-results-wrap">
      <div class="jobs-results" id="jobsResults">
        <?php foreach ($jobs as $job): ?>
          <?php
            $companyName = $job->company_name ?: 'Nhà tuyển dụng';
            $urgent = jobsPageIsUrgent($job);
            $typeLabel = jobsPageTypeLabel($job);
            $logoText = jobsPageInitials($companyName);
            $company_logo = $job->logo_url ?: $logoText;
            $searchTitle = mb_strtolower(trim($job->title.' '.$companyName.' '.$job->job_category_name), 'UTF-8');
            $jobUrl = general::getInstance()->permalink((int)$job->id, 'job_post');
          ?>
          <a
            href="<?php echo jobsPageH($jobUrl); ?>"
            class="job-box<?php echo $urgent ? ' is-urgent' : ''; ?>"
            data-title="<?php echo jobsPageH($searchTitle); ?>"
            data-location="<?php echo (int)$job->province_id; ?>"
            data-salary="<?php echo (int)$job->salary_id; ?>"
            data-experience="<?php echo jobsPageH($job->experience_years ?? ''); ?>"
            data-industry="<?php echo (int)$job->job_category_id; ?>"
            data-urgent="<?php echo $urgent ? 'urgent' : 'normal'; ?>"
            aria-label="Xem chi tiết việc làm <?php echo jobsPageH($job->title); ?>"
          >
            <span class="job-urgent-badge"><i class="ti ti-bolt"></i> <?php echo jobsPageH($typeLabel ?: 'Tuyển gấp'); ?></span>
            <div class="job-box-head">
              <div class="job-logo" style="background:#eef6ff;color:#0d4e96">
                <?php if(!empty($job->logo_url)){ ?>
                  <img src="<?php echo XC_URL .'/'.jobsPageH($company_logo); ?>" alt="<?php echo jobsPageH($companyName); ?>">
                <?php }else{ ?>
                  <span class="job-logo-text"><?php echo jobsPageH($logoText); ?></span>
                <?php } ?>
              </div>
              <div>
                <h2 class="job-box-title"><?php echo jobsPageH($job->title); ?></h2>
                <div class="job-box-company"><i class="ti ti-building"></i> <?php echo jobsPageH($companyName); ?></div>
              </div>
            </div>
            <div class="job-box-tags">
              <span class="job-box-tag"><i class="ti ti-map-pin"></i><?php echo jobsPageH($job->province_name ?: 'Toàn quốc'); ?></span>
              <span class="job-box-tag"><i class="ti ti-briefcase"></i><?php echo jobsPageH($job->job_category_name ?: 'Chưa phân ngành'); ?></span>
              <span class="job-box-tag"><i class="ti ti-cash"></i><?php echo jobsPageH($job->salary_name ?: 'Thỏa thuận'); ?></span>
            </div>
            <div class="job-box-deadline"><i class="ti ti-calendar-due"></i> Hạn nộp hồ sơ: <?php echo jobsPageH(jobsPageDate($job->deadline)); ?></div>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="jobs-empty" id="jobsEmpty" style="<?php echo count($jobs) ? 'display:none' : 'display:block'; ?>">Không có việc làm phù hợp với bộ lọc đã chọn.</div>

      <?php if($total_pages > 1): ?>
        <div class="jobs-pagination" id="jobsPagination" aria-label="Phân trang việc làm">
          <a class="jobs-page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" id="jobsPrevPage" aria-label="Trang trước" href="<?php echo $page > 1 ? jobsPageH(jobsPageUrl($page - 1)) : '#'; ?>"><i class="ti ti-chevron-left"></i></a>
          <div class="jobs-pagination-pages" id="jobsPaginationPages">
            <?php foreach(jobsPagePaginationItems($page, $total_pages) as $item): ?>
              <?php if($item === '...'): ?>
                <span class="jobs-page-btn disabled" aria-hidden="true">...</span>
              <?php else: ?>
                <a class="jobs-page-btn <?php echo (int)$item === $page ? 'active' : ''; ?>" href="<?php echo jobsPageH(jobsPageUrl((int)$item)); ?>" aria-label="Trang <?php echo (int)$item; ?>"><?php echo (int)$item; ?></a>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <a class="jobs-page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" id="jobsNextPage" aria-label="Trang sau" href="<?php echo $page < $total_pages ? jobsPageH(jobsPageUrl($page + 1)) : '#'; ?>"><i class="ti ti-chevron-right"></i></a>
        </div>
      <?php endif; ?>
    </section>
  </div>
</main>

<script>
  (function () {
    var form = document.querySelector('.job-search-panel');
    if (!form) return;
    Array.prototype.slice.call(form.querySelectorAll('select')).forEach(function (select) {
      select.addEventListener('change', function () {
        form.submit();
      });
    });
  })();
</script>

<?php require "footer.php"; ?>
