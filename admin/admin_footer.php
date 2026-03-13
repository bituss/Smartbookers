  </main><!-- /.admin-main -->
</div><!-- /.admin-wrap -->

<script>
(function(){
  var burger  = document.getElementById('adminBurger');
  var sidebar = document.getElementById('adminSidebar');
  var overlay = document.getElementById('adminOverlay');
  if(!burger) return;

  function toggle(){
    var open = sidebar.classList.toggle('is-open');
    overlay.classList.toggle('is-open', open);
    burger.textContent = open ? '✕' : '☰';
  }
  function close(){
    sidebar.classList.remove('is-open');
    overlay.classList.remove('is-open');
    burger.textContent = '☰';
  }

  burger.addEventListener('click', toggle);
  overlay.addEventListener('click', close);

  // sidebar linkre kattintva is zárjuk mobilon
  sidebar.querySelectorAll('nav a').forEach(function(a){
    a.addEventListener('click', close);
  });
})();
</script>
</body>
</html>
