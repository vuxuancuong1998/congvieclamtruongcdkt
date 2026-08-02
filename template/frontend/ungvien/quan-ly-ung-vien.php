<?php require "header.php"; ?>
<?php
if (!isset($candidates) || !is_array($candidates)) {
  $candidates = [
    [
      'id' => 1,
      'name' => 'Nguyễn Thị Lan',
      'role' => 'Lập trình viên Java',
      'industry' => 'it',
      'city' => 'Hà Nội',
      'exp' => '3 năm',
      'exp_years' => 3,
      'is_student' => true,
      'salary' => '12-18tr',
      'applied' => '2 giờ trước',
      'status' => 'new',
      'score' => '4.8',
      'skills' => ['Java', 'Spring', 'MySQL'],
      'online' => true,
      'color' => '#0d4e96',
    ],
    [
      'id' => 2,
      'name' => 'Trần Văn Hùng',
      'role' => 'Senior ReactJS',
      'industry' => 'it',
      'city' => 'TP.HCM',
      'exp' => '5 năm',
      'exp_years' => 5,
      'is_student' => false,
      'salary' => '20-30tr',
      'applied' => '5 giờ trước',
      'status' => 'review',
      'score' => '4.6',
      'skills' => ['React', 'TypeScript', 'Redux'],
      'online' => false,
      'color' => '#1565c0',
    ],
    [
      'id' => 3,
      'name' => 'Lê Thị Mai',
      'role' => 'UI/UX Designer',
      'industry' => 'design',
      'city' => 'Đà Nẵng',
      'exp' => '2 năm',
      'exp_years' => 2,
      'is_student' => true,
      'salary' => '10-15tr',
      'applied' => '1 ngày trước',
      'status' => 'interview',
      'score' => '4.4',
      'skills' => ['Figma', 'CSS', 'HTML'],
      'online' => true,
      'color' => '#6a1b9a',
    ],
    [
      'id' => 4,
      'name' => 'Phạm Quốc Bảo',
      'role' => 'Data Engineer',
      'industry' => 'data',
      'city' => 'Bình Dương',
      'exp' => '4 năm',
      'exp_years' => 4,
      'is_student' => false,
      'salary' => '18-25tr',
      'applied' => '2 ngày trước',
      'status' => 'pass',
      'score' => '4.7',
      'skills' => ['Spark', 'Kafka', 'SQL'],
      'online' => false,
      'color' => '#00695c',
    ],
    [
      'id' => 5,
      'name' => 'Hoàng Thị Thu',
      'role' => 'QA Engineer',
      'industry' => 'qa',
      'city' => 'Cần Thơ',
      'exp' => '1 năm',
      'exp_years' => 1,
      'is_student' => true,
      'salary' => '8-12tr',
      'applied' => '3 ngày trước',
      'status' => 'reject',
      'score' => '4.1',
      'skills' => ['Selenium', 'JIRA', 'Agile'],
      'online' => true,
      'color' => '#c62828',
    ],
    [
      'id' => 6,
      'name' => 'Vũ Minh Khoa',
      'role' => 'Product Manager',
      'industry' => 'product',
      'city' => 'Đồng Nai',
      'exp' => '6+ năm',
      'exp_years' => 6,
      'is_student' => false,
      'salary' => '25-35tr',
      'applied' => '1 tuần trước',
      'status' => 'review',
      'score' => '4.9',
      'skills' => ['Scrum', 'OKR', 'Jira'],
      'online' => false,
      'color' => '#e65100',
    ],
  ];
}

$statusLabels = [
  'new' => 'Mới nộp',
  'review' => 'Đang xét',
  'interview' => 'Phỏng vấn',
  'pass' => 'Đã nhận',
  'reject' => 'Từ chối',
];
$statusClasses = [
  'new' => 'status-new',
  'review' => 'status-review',
  'interview' => 'status-interview',
  'pass' => 'status-pass',
  'reject' => 'status-reject',
];

$totalCandidates = count($candidates);
$studentCandidates = count(array_filter($candidates, function ($candidate) { return !empty($candidate['is_student']); }));
$experiencedCandidates = count(array_filter($candidates, function ($candidate) { return (int)($candidate['exp_years'] ?? 0) > 2; }));

function h($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function candidate_initials($name) {
  $parts = preg_split('/\s+/u', trim((string)$name));
  if (!$parts || !count($parts)) return '';
  $first = mb_substr($parts[0], 0, 1, 'UTF-8');
  $last = mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8');
  return mb_strtoupper($first . $last, 'UTF-8');
}
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
        <div class="hero-stat-num"><?= $totalCandidates ?></div>
        <div class="hero-stat-label">Tổng ứng viên</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-num"><?= $studentCandidates ?></div>
        <div class="hero-stat-label">Ứng viên là sinh viên</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-num"><?= $experiencedCandidates ?></div>
        <div class="hero-stat-label">Ứng viên trên 2 năm kinh nghiệm</div>
      </div>
    </div>
  </div>
</div>
<!-- TOOLBAR -->
<div class="toolbar">
  <div class="toolbar-inner">
    <div class="toolbar-search">
      <i class="ti ti-search"></i>
      <input type="text" placeholder="Tìm tên, email, kỹ năng..." id="searchInput"/>
    </div>
    <div class="toolbar-filter">
      <select class="filter-select" id="filterLocation">
        <option value="">Tất cả địa điểm</option>
        <option value="TP.HCM">TP.HCM</option>
        <option value="Hà Nội">Hà Nội</option>
        <option value="Đà Nẵng">Đà Nẵng</option>
        <option value="Bình Dương">Bình Dương</option>
        <option value="Cần Thơ">Cần Thơ</option>
        <option value="Đồng Nai">Đồng Nai</option>
      </select>
      <select class="filter-select" id="filterExp">
        <option value="">Kinh nghiệm</option>
        <option value="0">Fresher (0-1 năm)</option>
        <option value="2">Junior (1-3 năm)</option>
        <option value="4">Senior (3-5 năm)</option>
        <option value="6">Expert (5+ năm)</option>
      </select>
      <button class="btn-filter" id="applyFilter"><i class="ti ti-filter"></i> Lọc</button>
    </div>
    <div class="result-count" id="resultCount"><strong><?= $totalCandidates ?></strong> ứng viên</div>
  </div>
</div>

<!-- MAIN -->
<div class="main-wrap">
  <!-- INDUSTRY TABS -->
  <div class="industry-slider" aria-label="Bộ lọc ngành nghề">
    <button type="button" class="industry-slide-btn" id="industryPrev" aria-label="Cuộn ngành nghề sang trái"><i class="ti ti-chevron-left"></i></button>
    <div class="industry-slider-viewport">
      <div class="status-tabs" id="industryTabs">
        <button class="status-tab active" data-industry="all">Tất cả <span class="tab-count" data-industry-count="all">0</span></button>
        <button class="status-tab" data-industry="it">CNTT - Phần mềm <span class="tab-count" data-industry-count="it">0</span></button>
        <button class="status-tab" data-industry="design">Thiết kế UI/UX <span class="tab-count" data-industry-count="design">0</span></button>
        <button class="status-tab" data-industry="data">Dữ liệu <span class="tab-count" data-industry-count="data">0</span></button>
        <button class="status-tab" data-industry="qa">Kiểm thử phần mềm <span class="tab-count" data-industry-count="qa">0</span></button>
        <button class="status-tab" data-industry="product">Quản lý sản phẩm <span class="tab-count" data-industry-count="product">0</span></button>
        <button class="status-tab" data-industry="marketing">Marketing <span class="tab-count" data-industry-count="marketing">0</span></button>
        <button class="status-tab" data-industry="sales">Kinh doanh <span class="tab-count" data-industry-count="sales">0</span></button>
        <button class="status-tab" data-industry="hr">Nhân sự <span class="tab-count" data-industry-count="hr">0</span></button>
        <button class="status-tab" data-industry="accounting">Kế toán <span class="tab-count" data-industry-count="accounting">0</span></button>
      </div>
    </div>
    <button type="button" class="industry-slide-btn" id="industryNext" aria-label="Cuộn ngành nghề sang phải"><i class="ti ti-chevron-right"></i></button>
  </div>

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
          $skills = $candidate['skills'] ?? [];
          $status = $candidate['status'] ?? 'new';
          $statusLabel = $statusLabels[$status] ?? $status;
          $statusClass = $statusClasses[$status] ?? 'status-new';
          $searchText = trim(($candidate['name'] ?? '') . ' ' . ($candidate['role'] ?? '') . ' ' . implode(' ', $skills));
        ?>
        <article
          class="cand-card"
          data-industry="<?= h($candidate['industry'] ?? '') ?>"
          data-city="<?= h($candidate['city'] ?? '') ?>"
          data-search="<?= h(mb_strtolower($searchText, 'UTF-8')) ?>"
        >
          <div class="cand-card-top">
            <div class="cand-rank"><?= h($candidate['id'] ?? '') ?></div>
            <div class="cand-card-actions">
              <button type="button" class="card-action-btn fav" title="Yêu thích"><i class="ti ti-heart"></i></button>
              <button type="button" class="card-action-btn" title="Tải CV"><i class="ti ti-download"></i></button>
            </div>
            <?php $cvUrl2 = isset($candidate['avatar_url']) && trim($candidate['avatar_url']) !== '' ? XC_URL . '/' . ltrim($candidate['avatar_url'], '/') : '#'; ?>
            <div class="cand-avatar <?= ($cvUrl2 !== '#') ? 'has-img' : '' ?>" style="background:<?= h($candidate['color'] ?? '#0d4e96') ?>">
              <?= ($cvUrl2 !== '#') ? '<img src="' . h($cvUrl2) . '" alt="' . h($candidate['name'] ?? '') . '">' : h(candidate_initials($candidate['name'] ?? '')) ?>
              <div class="<?= !empty($candidate['online']) ? 'online-dot' : 'offline-dot' ?>"></div>
            </div>
            <div class="cand-name"><?= h($candidate['name'] ?? '') ?></div>
            <div class="cand-role"><?= h($candidate['role'] ?? '') ?></div>
            <div class="cand-meta">
              <div class="cand-meta-row"><i class="ti ti-map-pin"></i><?= h($candidate['city'] ?? '') ?></div>
              <div class="cand-meta-row"><i class="ti ti-clock"></i><?= h($candidate['exp'] ?? '') ?> kinh nghiệm</div>
            </div>
          </div>
          <div class="cand-card-body">
            <div class="cand-info-grid">
              <div class="cand-info-item"><div class="cand-info-label">Mức lương</div><div class="cand-info-value"><?= h($candidate['salary'] ?? '') ?></div></div>
              <div class="cand-info-item"><div class="cand-info-label">Ngày nộp</div><div class="cand-info-value"><?= h($candidate['applied'] ?? '') ?></div></div>
            </div>
            <div class="cand-skills">
              <?php foreach ($skills as $skill): ?>
                <span class="skill-tag"><?= h($skill) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="cand-card-footer">
            <span class="cand-status-badge <?= h($statusClass) ?>"><?= h($statusLabel) ?></span>
            <div style="display:flex;align-items:center;gap:8px">
              <div class="cand-score"><i class="ti ti-star-filled"></i><?= h($candidate['score'] ?? '') ?></div>
              <button type="button" class="btn-view"><i class="ti ti-eye"></i> Xem</button>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- PAGINATION -->
  <div class="pagination-wrap" id="paginationWrap">
    <div class="page-info" id="pageInfo"></div>
    <div class="pagination" id="pagination"></div>
    <div class="page-size-wrap">
      Hiển thị
      <select id="pageSizeSelect">
        <option value="8">8</option>
        <option value="12" selected>12</option>
        <option value="20">20</option>
        <option value="28">28</option>
      </select>
      / trang
    </div>
  </div>
</div>

<script>
(function(){
  var cards = Array.prototype.slice.call(document.querySelectorAll('.cand-card'));
  var currentPage = 1;
  var pageSize = 12;
  var currentIndustry = 'all';
  var currentLocation = 'all';
  var searchQ = '';

  function normalize(value){
    return (value || '').toString().trim().toLowerCase();
  }

  function getFiltered(){
    var q = normalize(searchQ);
    return cards.filter(function(card){
      var industryOk = currentIndustry === 'all' || card.dataset.industry === currentIndustry;
      var locationOk = currentLocation === 'all' || card.dataset.city === currentLocation;
      var searchOk = !q || normalize(card.dataset.search).indexOf(q) !== -1;
      return industryOk && locationOk && searchOk;
    });
  }

  function updateIndustryCounts(){
    var counts = {all: cards.length};
    cards.forEach(function(card){
      var key = card.dataset.industry || '';
      if (!key) return;
      counts[key] = (counts[key] || 0) + 1;
    });
    document.querySelectorAll('[data-industry-count]').forEach(function(el){
      var key = el.getAttribute('data-industry-count');
      el.textContent = counts[key] || 0;
    });
  }

  function renderPagination(total){
    var totalPages = Math.ceil(total / pageSize);
    var info = document.getElementById('pageInfo');
    var pg = document.getElementById('pagination');
    if (!info || !pg) return;
    if (!total) {
      info.innerHTML = 'Hiển thị <strong>0</strong> / <strong>0</strong>';
      pg.innerHTML = '';
      return;
    }
    var start = (currentPage - 1) * pageSize + 1;
    var end = Math.min(currentPage * pageSize, total);
    info.innerHTML = 'Hiển thị <strong>' + start + '-' + end + '</strong> / <strong>' + total + '</strong>';

    var totalButtons = '';
    totalButtons += '<button class="page-btn" data-page="' + (currentPage - 1) + '" ' + (currentPage === 1 ? 'disabled' : '') + '><i class="ti ti-chevron-left"></i></button>';
    for (var i = 1; i <= totalPages; i++) {
      totalButtons += '<button class="page-btn' + (i === currentPage ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
    }
    totalButtons += '<button class="page-btn" data-page="' + (currentPage + 1) + '" ' + (currentPage === totalPages ? 'disabled' : '') + '><i class="ti ti-chevron-right"></i></button>';
    pg.innerHTML = totalButtons;
    pg.querySelectorAll('[data-page]').forEach(function(btn){
      btn.addEventListener('click', function(){
        var page = parseInt(btn.getAttribute('data-page'), 10);
        if (!page || page < 1 || page > totalPages) return;
        currentPage = page;
        renderCards(getFiltered());
      });
    });
  }

  function renderCards(list){
    var start = (currentPage - 1) * pageSize;
    var end = start + pageSize;
    var visible = list.slice(start, end);
    cards.forEach(function(card){ card.style.display = 'none'; });
    visible.forEach(function(card){ card.style.display = ''; });
    var resultCount = document.getElementById('resultCount');
    if (resultCount) resultCount.innerHTML = '<strong>' + list.length + '</strong> ứng viên';
    renderPagination(list.length);
  }

  function refresh(){
    currentPage = 1;
    renderCards(getFiltered());
  }

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

  document.querySelectorAll('#industryTabs .status-tab').forEach(function(tab){
    tab.addEventListener('click', function(){
      document.querySelectorAll('#industryTabs .status-tab').forEach(function(item){ item.classList.remove('active'); });
      tab.classList.add('active');
      currentIndustry = tab.dataset.industry || 'all';
      refresh();
    });
  });

  var searchInput = document.getElementById('searchInput');
  if (searchInput) {
    searchInput.addEventListener('input', function(){
      searchQ = searchInput.value;
      refresh();
    });
  }

  var applyFilter = document.getElementById('applyFilter');
  if (applyFilter) {
    applyFilter.addEventListener('click', function(){
      var selectedLocation = document.getElementById('filterLocation');
      currentLocation = selectedLocation && selectedLocation.value ? selectedLocation.value : 'all';
      refresh();
    });
  }

  var pageSizeSelect = document.getElementById('pageSizeSelect');
  if (pageSizeSelect) {
    pageSizeSelect.addEventListener('change', function(){
      pageSize = parseInt(pageSizeSelect.value, 10) || 12;
      refresh();
    });
  }

  document.querySelectorAll('.card-action-btn.fav').forEach(function(btn){
    btn.addEventListener('click', function(event){
      event.stopPropagation();
      btn.classList.toggle('active');
      var icon = btn.querySelector('i');
      if (icon) icon.className = btn.classList.contains('active') ? 'ti ti-heart-filled' : 'ti ti-heart';
    });
  });

  var industryTrack = document.getElementById('industryTabs');
  var industryPrev = document.getElementById('industryPrev');
  var industryNext = document.getElementById('industryNext');
  if (industryPrev) industryPrev.addEventListener('click', function(){ scrollIndustryTabs(-1); });
  if (industryNext) industryNext.addEventListener('click', function(){ scrollIndustryTabs(1); });
  if (industryTrack) industryTrack.addEventListener('scroll', updateIndustrySliderControls);
  window.addEventListener('resize', updateIndustrySliderControls);

  updateIndustryCounts();
  refresh();
  updateIndustrySliderControls();
})();
</script>

<?php require "footer.php"; ?>