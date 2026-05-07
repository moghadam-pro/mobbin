$(function () {
  // Copy folder URL
  $('.copy-btn').on('click', function (e) {
    e.preventDefault();
    var url = this.dataset.url;
    if (!url) return;

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(() => {
        this.classList.add('copied');
        this.setAttribute('aria-label', 'Copied');
        setTimeout(() => this.classList.remove('copied'), 1200);
      });
    } else {
      var temp = document.createElement('input');
      temp.value = url;
      document.body.appendChild(temp);
      temp.select();
      document.execCommand('copy');
      document.body.removeChild(temp);
    }
  });

  // Lightbox gallery
  var galleryItems = Array.from(document.querySelectorAll('.gallery-item'));
  var lightbox = document.getElementById('lightbox');
  var lightboxImg = document.getElementById('lightboxImage');
  var lightboxClose = document.getElementById('lightboxClose');
  var lightboxPrev = document.getElementById('lightboxPrev');
  var lightboxNext = document.getElementById('lightboxNext');
  var currentIndex = 0;

  function openLightbox(index) {
    if (!lightbox || !lightboxImg) return;
    currentIndex = index;
    var target = galleryItems[currentIndex];
    if (!target) return;
    lightboxImg.src = target.dataset.full;
    lightbox.classList.add('open');
    lightbox.setAttribute('aria-hidden', 'false');
  }

  function closeLightbox() {
    if (!lightbox || !lightboxImg) return;
    lightbox.classList.remove('open');
    lightbox.setAttribute('aria-hidden', 'true');
    lightboxImg.src = '';
  }

  function showNext(step) {
    if (!galleryItems.length) return;
    currentIndex = (currentIndex + step + galleryItems.length) % galleryItems.length;
    openLightbox(currentIndex);
  }

  galleryItems.forEach(function (item, index) {
    item.addEventListener('click', function () {
      openLightbox(index);
    });
  });

  if (lightboxClose) {
    lightboxClose.addEventListener('click', closeLightbox);
  }
  if (lightboxPrev) {
    lightboxPrev.addEventListener('click', function () {
      showNext(-1);
    });
  }
  if (lightboxNext) {
    lightboxNext.addEventListener('click', function () {
      showNext(1);
    });
  }

  if (lightbox) {
    lightbox.addEventListener('click', function (e) {
      if (e.target === lightbox) {
        closeLightbox();
      }
    });
  }

  document.addEventListener('keydown', function (e) {
    if (!lightbox || !lightbox.classList.contains('open')) return;
    if (e.key === 'Escape') {
      closeLightbox();
    }
    if (e.key === 'ArrowRight') {
      showNext(1);
    }
    if (e.key === 'ArrowLeft') {
      showNext(-1);
    }
  });
});
