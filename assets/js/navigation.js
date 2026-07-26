var sidebarEl = document.querySelector('.sidebar');
  var overlayEl = document.getElementById('sidebar-overlay');
  var btnFilters = document.getElementById('btn-filters-mobile');
  function closeSidebar() { sidebarEl.classList.remove('open'); overlayEl.classList.remove('open'); }
  if (btnFilters) btnFilters.addEventListener('click', function() { sidebarEl.classList.add('open'); overlayEl.classList.add('open'); });
  if (overlayEl) overlayEl.addEventListener('click', closeSidebar);
