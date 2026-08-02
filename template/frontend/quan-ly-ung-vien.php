<?php require "header.php"; ?>
<?php
$candidates = isset($candidates) && is_array($candidates) ? $candidates : array();
$candidate_provinces = isset($candidate_provinces) && is_array($candidate_provinces) ? $candidate_provinces : array();
$candidate_categories = isset($candidate_categories) && is_array($candidate_categories) ? $candidate_categories : array();
$candidate_salaries = isset($candidate_salaries) && is_array($candidate_salaries) ? $candidate_salaries : array();
$candidate_degrees = isset($candidate_degrees) && is_array($candidate_degrees) ? $candidate_degrees : array();
$candidate_work_types = isset($candidate_work_types) && is_array($candidate_work_types) ? $candidate_work_types : array();
$candidate_filters = isset($candidate_filters) && is_array($candidate_filters) ? $candidate_filters : array();
$candidate_page = isset($candidate_page) ? (int)$candidate_page : 1;
$candidate_total_pages = isset($candidate_total_pages) ? (int)$candidate_total_pages : 1;
$candidate_total = isset($candidate_total) ? (int)$candidate_total : count($candidates);

function candidatePageH($value){
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function candidatePageUrl($targetPage){
  $params = $_GET;
  $params['page'] = $targetPage;
  return XC_URL.'/quan-ly-ung-vien.html?'.http_build_query($params);
}

function candidatePagePaginationItems($currentPage, $totalPages){
  $currentPage = max(1, (int)$currentPage);
  $totalPages = max(1, (int)$totalPages);
  if($totalPages <= 7){
    return range(1, $totalPages);
  }
  if($currentPage <= 3){
    $pages = range(1, 5);
  }elseif($currentPage >= $totalPages - 2){
    $pages = range($totalPages - 4, $totalPages);
  }else{
    $pages = range($currentPage - 2, $currentPage + 1);
  }
  if(end($pages) < $totalPages - 1){
    $pages[] = '...';
  }
  if(!in_array($totalPages, $pages, true)){
    $pages[] = $totalPages;
  }
  return $pages;
}

function getCandidateName($candidate) {
  return $candidate->full_name ?? 'Ứng viên';
}

function getCandidateRole($candidate) {
  return $candidate->desired_position ?? 'Đang cập nhật';
}

function getCandidateCity($candidate) {
  return $candidate->desired_province_name ?: $candidate->province_name ?: 'Toàn quốc';
}

function getCandidateSalary($candidate) {
  return $candidate->salary_name ?: 'Thỏa thuận';
}

function getCandidateExpText($candidate) {
  $years = intval($candidate->experience_years ?? 0);
  return $years > 0 ? $years . ' năm' : 'Chưa có';
}

function getCandidateInitials($name) {
  $name = trim((string)$name);
  if ($name === '') return 'UV';
  $parts = preg_split('/\s+/u', $name);
  $letters = '';
  foreach ($parts as $part) {
    if ($part !== '') $letters .= mb_substr($part, 0, 1, 'UTF-8');
    if (mb_strlen($letters, 'UTF-8') >= 2) break;
  }
  return mb_strtoupper($letters ?: mb_substr($name, 0, 2, 'UTF-8'), 'UTF-8');
}

function getCandidateColor($candidate) {
  $colors = ['#0d4e96', '#1565c0', '#6a1b9a', '#00695c', '#c62828', '#e65100'];
  return $colors[intval($candidate->id ?? 1) % count($colors)];
}

function getCandidateApplied($candidate) {
  $date = $candidate->created_at ?? null;
  if (!$date) return 'Mới nộp';
  $time = strtotime($date);
  if (!$time) return 'Mới nộp';
  $seconds = max(0, time() - $time);
  $minutes = floor($seconds / 60);
  if ($minutes < 1) return 'Vừa xong';
  if ($minutes < 60) return $minutes.' phút trước';
  $hours = floor($minutes / 60);
  if ($hours < 24) return $hours.' giờ trước';
  $days = floor($hours / 24);
  return $days.' ngày trước';
}

function getCandidateSkills($candidate) {
  $skillsText = trim((string)($candidate->soft_skills ?? ''));
  if ($skillsText === '') return array();

  $decoded = json_decode($skillsText, true);
  if (is_array($decoded)) {
    $skills = array();
    foreach ($decoded as $item) {
      $label = '';
      if (is_array($item)) {
        $label = trim((string)($item['skill'] ?? $item['name'] ?? ''));
      } else {
        $label = trim((string)$item);
      }
      if ($label !== '') {
        $skills[] = $label;
      }
    }
    return array_values(array_unique($skills));
  }

  return array_values(array_filter(array_map('trim', preg_split('/[\r\n,;|]+/u', $skillsText)), function($value) {
    return $value !== '';
  }));
}

global $db;
$db->query("SELECT COUNT(id) AS total FROM hicrm_candidates WHERE status = 3 AND is_seeking = 1");
$dbTotal = intval($db->fetch_object(true)->total);

$db->query("SELECT COUNT(ca.id) AS total
            FROM hicrm_candidates ca
            LEFT JOIN hicrm_users u ON u.id = ca.user_id
            WHERE ca.status = 3 AND ca.is_seeking = 1 AND u.user_group = 4");
$dbStudents = intval($db->fetch_object(true)->total);

$db->query("SELECT COUNT(ca.id) AS total FROM hicrm_candidates ca WHERE ca.status = 3 AND ca.is_seeking = 1 AND 
            COALESCE((SELECT FLOOR(SUM(DATEDIFF(COALESCE(ce.end_date, CURDATE()), ce.start_date)) / 365)
                      FROM hicrm_candidate_experiences ce WHERE ce.candidate_id = ca.id), 0) > 2");
$dbExperienced = intval($db->fetch_object(true)->total);
?>

<!-- PAGE HERO -->
<div class="page-hero">
  <div class="page-hero-inner">
    <div class="page-hero-left">
      <h1><i class="ti ti-users" style="font-size:22px;vertical-align:-3px;margin-right:8px"></i>Quản lý ứng viên</h1>
      <p><i class="ti ti-building"></i> Tìm kiếm ứng viên nhanh <i class="ti ti-briefcase"></i> Cơ hội việc làm cho sinh viên ra trường</p>
    </div>
    <div class="page-hero-stats">
      <div class="hero-stat">
        <div class="hero-stat-num"><?= $dbTotal ?></div>
        <div class="hero-stat-label">Tổng ứng viên</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-num"><?= $dbStudents ?></div>
        <div class="hero-stat-label">Ứng viên là sinh viên</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-num"><?= $dbExperienced ?></div>
        <div class="hero-stat-label">Ứng viên > 2 năm KN</div>
      </div>
    </div>
  </div>
</div>

<!-- TOOLBAR & FILTERS -->
<form class="candidate-search-panel" method="get" action="<?php echo XC_URL; ?>/quan-ly-ung-vien.html" id="candidateFilterForm">
  <!-- Hidden input for category -->
  <input type="hidden" name="job_category_id" id="jobCategoryIdInput" value="<?php echo (int)($candidate_filters['job_category_id'] ?? 0); ?>"/>

  <div class="toolbar">
    <div class="toolbar-inner">
      <div class="toolbar-search">
        <i class="ti ti-search"></i>
        <input type="text" name="keyword" value="<?php echo candidatePageH($candidate_filters['keyword'] ?? ''); ?>" placeholder="Tìm tên, email, kỹ năng..." id="searchInput"/>
      </div>
      <div class="toolbar-filter">
        <select class="filter-select" name="province_id" id="filterLocation">
          <option value="">Tất cả địa điểm</option>
          <?php foreach ($candidate_provinces as $province): ?>
            <option value="<?php echo (int)$province->id; ?>" <?php echo (int)($candidate_filters['province_id'] ?? 0) === (int)$province->id ? 'selected' : ''; ?>><?php echo candidatePageH($province->province_name); ?></option>
          <?php endforeach; ?>
        </select>
        <select class="filter-select" name="degree" id="filterDegree">
          <option value="">Tất cả học vấn</option>
          <?php foreach ($candidate_degrees as $deg): ?>
            <option value="<?php echo candidatePageH($deg->degree); ?>" <?php echo ($candidate_filters['degree'] ?? '') === $deg->degree ? 'selected' : ''; ?>><?php echo candidatePageH($deg->degree); ?></option>
          <?php endforeach; ?>
        </select>
        <select class="filter-select" name="work_type" id="filterWorkType">
          <option value="">Tất cả hình thức</option>
          <?php foreach ($candidate_work_types as $wt): ?>
            <option value="<?php echo candidatePageH($wt->desired_work_type); ?>" <?php echo ($candidate_filters['work_type'] ?? '') === $wt->desired_work_type ? 'selected' : ''; ?>><?php echo candidatePageH($wt->desired_work_type); ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-filter" id="applyFilter"><i class="ti ti-filter"></i> Lọc</button>
      </div>
      <div class="result-count" id="resultCount"><strong><?php echo $candidate_total; ?></strong> ứng viên</div>
    </div>
  </div>

  <!-- MAIN -->
  <div class="main-wrap">
    <!-- INDUSTRY TABS -->
    <div class="industry-slider" aria-label="Bộ lọc ngành nghề">
      <button type="button" class="industry-slide-btn" id="industryPrev" aria-label="Cuộn ngành nghề sang trái"><i class="ti ti-chevron-left"></i></button>
      <div class="industry-slider-viewport">
        <div class="status-tabs" id="industryTabs">
          <button type="button" class="status-tab<?php echo (int)($candidate_filters['job_category_id'] ?? 0) === 0 ? ' active' : ''; ?>" data-category-id="0">Tất cả</button>
          <?php foreach ($candidate_categories as $cat): ?>
            <button type="button" class="status-tab<?php echo (int)($candidate_filters['job_category_id'] ?? 0) === (int)$cat->id ? ' active' : ''; ?>" data-category-id="<?php echo (int)$cat->id; ?>">
              <?php echo candidatePageH($cat->job_category_name); ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="button" class="industry-slide-btn" id="industryNext" aria-label="Cuộn ngành nghề sang phải"><i class="ti ti-chevron-right"></i></button>
    </div>
</form>

    <!-- CANDIDATE GRID -->
    <div class="cand-grid" id="candGrid">
      <?php if (empty($candidates)): ?>
        <div class="empty-state" style="grid-column:1/-1">
          <i class="ti ti-user-search"></i>
          <h3>Không tìm thấy ứng viên</h3>
          <p>Chưa có dữ liệu ứng viên để hiển thị.</p>
        </div>
      <?php else: ?>
        <?php foreach ($candidates as $candidate): ?>
          <?php
            $name = getCandidateName($candidate);
            $role = getCandidateRole($candidate);
            $city = getCandidateCity($candidate);
            $salary = getCandidateSalary($candidate);
            $expText = getCandidateExpText($candidate);
            $initials = getCandidateInitials($name);
            $color = getCandidateColor($candidate);
            $appliedText = getCandidateApplied($candidate);
            
            $skills = getCandidateSkills($candidate);
            
            $detailUrl = general::getInstance()->permalink((int)($candidate->id ?? 0), 'candidate_profile');
            $cvUrl = isset($candidate->avatar_url) && trim($candidate->avatar_url) !== '' ? XC_URL . '/' . ltrim($candidate->avatar_url, '/') : '#';
          ?>
          <a href="<?php echo candidatePageH($detailUrl); ?>" class="cand-card" style="display:block;text-decoration:none;color:inherit;">
            <div class="cand-card-top">
              <!-- <div class="cand-rank"><?php echo (int)($candidate->id ?? 0); ?></div>
              <div class="cand-card-actions">
                <span class="card-action-btn fav" role="button" tabindex="0" title="Yêu thích"><i class="ti ti-heart"></i></span>
                <?php if($cvUrl !== '#'): ?>
                  <span class="card-action-btn card-action-link" role="link" tabindex="0" data-href="<?php echo candidatePageH($cvUrl); ?>" title="Tải CV"><i class="ti ti-download"></i></span>
                <?php endif; ?>
              </div> -->
              <div class="cand-avatar <?php echo ($cvUrl !== '#') ? 'has-img' : ''; ?>" style="background:<?php echo candidatePageH($color); ?>">
               
                <?php echo ($cvUrl !== '#') ? '<img src="' . candidatePageH($cvUrl) . '" alt="' . candidatePageH($name) . '">' : candidatePageH($initials); ?>
                <!-- <div class="online-dot"></div> -->
              </div>
              <div class="cand-name"><?php echo candidatePageH($name); ?></div>
              <div class="cand-role"><?php echo candidatePageH($role); ?></div>
              <div class="cand-meta">
                <div class="cand-meta-row"><i class="ti ti-map-pin"></i><?php echo candidatePageH($city); ?></div>
                <div class="cand-meta-row"><i class="ti ti-clock"></i><?php echo candidatePageH($expText); ?> kinh nghiệm</div>
              </div>
            </div>
            <div class="cand-card-body">
              <div class="cand-info-grid">
                <div class="cand-info-item"><div class="cand-info-label">Mức lương</div><div class="cand-info-value"><?php echo candidatePageH($salary); ?></div></div>
                <div class="cand-info-item"><div class="cand-info-label">Ngày nộp</div><div class="cand-info-value"><?php echo candidatePageH($appliedText); ?></div></div>
              </div>
              <!--  -->
            </div>
            <!-- <div class="cand-card-footer">
              <span class="cand-status-badge status-pass">Sẵn sàng làm việc</span>
              <div style="display:flex;align-items:center;gap:8px">
                <div class="cand-score"><i class="ti ti-star-filled"></i>5.0</div>
                <span class="btn-view"><i class="ti ti-eye"></i> Xem</span>
              </div>
            </div> -->
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- PAGINATION -->
    <?php if($candidate_total_pages > 1): ?>
      <div class="pagination-wrap" id="paginationWrap">
        <div class="page-info" id="pageInfo">
          Hiển thị <strong><?php echo ($candidate_page - 1) * 16 + 1; ?>-<?php echo min($candidate_page * 16, $candidate_total); ?></strong> / <strong><?php echo $candidate_total; ?></strong>
        </div>
        <div class="pagination" id="pagination">
          <a class="page-btn <?php echo $candidate_page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $candidate_page > 1 ? candidatePageH(candidatePageUrl($candidate_page - 1)) : '#'; ?>"><i class="ti ti-chevron-left"></i></a>
          <?php foreach(candidatePagePaginationItems($candidate_page, $candidate_total_pages) as $item): ?>
            <?php if($item === '...'): ?>
              <span class="page-btn disabled">...</span>
            <?php else: ?>
              <a class="page-btn <?php echo (int)$item === $candidate_page ? 'active' : ''; ?>" href="<?php echo candidatePageH(candidatePageUrl((int)$item)); ?>"><?php echo (int)$item; ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
          <a class="page-btn <?php echo $candidate_page >= $candidate_total_pages ? 'disabled' : ''; ?>" href="<?php echo $candidate_page < $candidate_total_pages ? candidatePageH(candidatePageUrl($candidate_page + 1)) : '#'; ?>"><i class="ti ti-chevron-right"></i></a>
        </div>
      </div>
    <?php endif; ?>
  </div>

<script>
(function(){
  var form = document.getElementById('candidateFilterForm');
  if (form) {
    Array.prototype.slice.call(form.querySelectorAll('select')).forEach(function(select) {
      select.addEventListener('change', function() {
        form.submit();
      });
    });
  }

  document.querySelectorAll('#industryTabs .status-tab').forEach(function(tab){
    tab.addEventListener('click', function(){
      var catId = tab.getAttribute('data-category-id');
      var input = document.getElementById('jobCategoryIdInput');
      if (input) {
        input.value = catId;
        form.submit();
      }
    });
  });

  function updateIndustrySliderControls(){
    var track = document.getElementById('industryTabs');
    var prev = document.getElementById('industryPrev');
    var next = document.getElementById('industryNext');
    if (!track || !prev || !next) return;
    var maxScroll = track.scrollWidth - track.clientWidth;
    prev.disabled = track.scrollLeft <= 2;
    next.disabled = track.scrollLeft >= maxScroll - 2;
  }

  function scrollIndustryTabs(direction){
    var track = document.getElementById('industryTabs');
    if (!track) return;
    var distance = Math.max(220, Math.floor(track.clientWidth * 0.75));
    track.scrollBy({left: direction * distance, behavior: 'smooth'});
  }

  var industryTrack = document.getElementById('industryTabs');
  var industryPrev = document.getElementById('industryPrev');
  var industryNext = document.getElementById('industryNext');
  if (industryPrev) industryPrev.addEventListener('click', function(){ scrollIndustryTabs(-1); });
  if (industryNext) industryNext.addEventListener('click', function(){ scrollIndustryTabs(1); });
  if (industryTrack) industryTrack.addEventListener('scroll', updateIndustrySliderControls);
  window.addEventListener('resize', updateIndustrySliderControls);

  updateIndustrySliderControls();

  document.querySelectorAll('.card-action-btn.fav').forEach(function(btn){
    btn.addEventListener('click', function(event){
      event.preventDefault();
      event.stopPropagation();
      btn.classList.toggle('active');
      var icon = btn.querySelector('i');
      if (icon) icon.className = btn.classList.contains('active') ? 'ti ti-heart-filled' : 'ti ti-heart';
    });
    btn.addEventListener('keydown', function(event){
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        btn.click();
      }
    });
  });

  document.querySelectorAll('.card-action-link').forEach(function(link){
    link.addEventListener('click', function(event){
      event.preventDefault();
      event.stopPropagation();
      var href = link.getAttribute('data-href');
      if (href) {
        window.open(href, '_blank', 'noopener');
      }
    });
    link.addEventListener('keydown', function(event){
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        link.click();
      }
    });
  });
})();
</script>

<?php require "footer.php"; ?>
