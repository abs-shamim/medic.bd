(function () {
  document.querySelectorAll('.wr-noop-link').forEach(function (link) {
    link.addEventListener('click', function (event) {
      event.preventDefault();
    });
  });
})();

(function () {
  const previewBox = document.getElementById('reviewSubmittedPreview');

  if (previewBox) {
    const backLink = document.querySelector('.wr-btn-row .wr-btn:not(.wr-btn-primary)');
    const redirectUrl = backLink ? backLink.getAttribute('href') : '';

    window.setTimeout(function () {
      window.location.href = redirectUrl;
    }, 60000);
  }
})();

  (function () {
    const anonymous = document.getElementById('isAnonymousReview');
    const nameInput = document.getElementById('review_name');
    const ratingInputs = document.querySelectorAll('input[name="review_rating"]');
    const ratingCards = document.querySelectorAll('.wr-rating-card');
    const ratingSummary = document.getElementById('reviewRatingSummary');
    const formDetails = document.getElementById('wrFormDetails');
    const firstRecaptcha = document.getElementById('wrFirstRecaptcha');
    const ratingStrip = document.getElementById('reviewRatingGrid');
    const mathAnswer = document.getElementById('mathAnswer');
    const mathCorrectAnswer = document.getElementById('mathCorrectAnswer');
    const mathStatus = document.getElementById('mathStatus');
    const submitReviewBtn = document.getElementById('submitReviewBtn');
    const reviewForm = document.querySelector('.wr-form');
    const reviewTitle = document.getElementById('review_title');
    const reviewComment = document.getElementById('review_comment');
    const autoTitleAlert = document.getElementById('autoTitleAlert');
    const plainTextFields = document.querySelectorAll('[data-plain-text]');
    let autoTitleConfirmed = false;
    const reviewCookieKey = reviewForm ? reviewForm.dataset.reviewCookieKey : '';

    const ratingMap = {
      '1': '1 - Poor',
      '2': '2 - Fair',
      '3': '3 - Good',
      '4': '4 - Very Good',
      '5': '5 - Excellent'
    };

    function syncNameField() {
      if (!anonymous || !nameInput) return;

      if (anonymous.checked) {
        nameInput.value = '';
        nameInput.disabled = true;
        nameInput.placeholder = 'Anonymous';
      } else {
        nameInput.disabled = false;
        nameInput.placeholder = 'Your name';
      }
    }

    function setReviewCookie(name, value, days) {
      const maxAge = days * 24 * 60 * 60;
      document.cookie = encodeURIComponent(name) + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAge + '; SameSite=Lax';
    }

    function getReviewCookie(name) {
      const encodedName = encodeURIComponent(name) + '=';
      const parts = document.cookie ? document.cookie.split(';') : [];

      for (let i = 0; i < parts.length; i++) {
        let item = parts[i].trim();

        if (item.indexOf(encodedName) === 0) {
          return decodeURIComponent(item.substring(encodedName.length));
        }
      }

      return '';
    }

    function openReviewDetails() {
      if (formDetails && !formDetails.classList.contains('is-open')) {
        formDetails.classList.add('is-open');
      }

      if (firstRecaptcha) {
        firstRecaptcha.style.display = 'none';
      }
    }

    function setReviewCookie(name, value, days) {
      const maxAge = days * 24 * 60 * 60;
      document.cookie = encodeURIComponent(name) + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAge + '; SameSite=Lax';
    }

    function getReviewCookie(name) {
      const encodedName = encodeURIComponent(name) + '=';
      const parts = document.cookie ? document.cookie.split(';') : [];

      for (let i = 0; i < parts.length; i++) {
        let item = parts[i].trim();

        if (item.indexOf(encodedName) === 0) {
          return decodeURIComponent(item.substring(encodedName.length));
        }
      }

      return '';
    }

    function openReviewDetails() {
      if (formDetails && !formDetails.classList.contains('is-open')) {
        formDetails.classList.add('is-open');
      }

      if (firstRecaptcha) {
        firstRecaptcha.style.display = 'none';
      }
    }

    function syncRatingCards() {
      let selected = '';

      ratingInputs.forEach(function (input) {
        if (input.checked) {
          selected = input.value;
        }
      });

      if (selected === '') {
        if (ratingStrip) {
          ratingStrip.className = 'wr-rating-strip';
        }

        ratingCards.forEach(function (card) {
          card.classList.remove('is-filled');
        });

        if (ratingSummary) {
          ratingSummary.innerHTML = '';
        }

        return;
      }

      const selectedNumber = parseInt(selected, 10);

      if (ratingStrip) {
        ratingStrip.className = 'wr-rating-strip rating-' + selected + ' wr-rating-animate';
        window.setTimeout(function () {
          if (ratingStrip) {
            ratingStrip.classList.remove('wr-rating-animate');
          }
        }, 260);
      }

      ratingCards.forEach(function (card) {
        const cardRating = parseInt(card.getAttribute('data-rating') || '0', 10);
        card.classList.toggle('is-filled', cardRating <= selectedNumber);
      });

      if (ratingSummary) {
        ratingSummary.innerHTML = 'Selected rating: <strong>' + ratingMap[selected] + '</strong>';
      }
    }

    const savedRating = getReviewCookie(reviewCookieKey);

    if (/^[1-5]$/.test(savedRating)) {
      const savedInput = document.getElementById('rating' + savedRating);

      if (savedInput) {
        savedInput.checked = true;
        if (mathAnswer) {
      mathAnswer.addEventListener('input', syncMathSubmit);
      syncMathSubmit();
    }

    if (reviewForm) {
      reviewForm.addEventListener('submit', handleAutoTitleBeforeSubmit);
    }

    plainTextFields.forEach(function (field) {
      field.addEventListener('input', function () {
        const fakeEvent = {
          preventDefault: function () {}
        };

        ensurePlainText(fakeEvent);
      });

      field.addEventListener('paste', function () {
        window.setTimeout(function () {
          const fakeEvent = {
            preventDefault: function () {}
          };

          ensurePlainText(fakeEvent);
        }, 30);
      });
    });

    if (reviewTitle) {
      reviewTitle.addEventListener('input', function () {
        autoTitleConfirmed = String(reviewTitle.value || '').trim() !== '';

        if (autoTitleAlert) {
          autoTitleAlert.classList.remove('is-visible');
        }
      });
    }

    syncRatingCards();
        openReviewDetails();
      }
    }

    function isPlainTextOnly(value) {
      const text = String(value || '');

      const blockedPatterns = [
        /<[^>]+>/i,
        /&lt;[^&]*&gt;/i,
        /https?:\/\/[^\s]+/i,
        /www\.[^\s]+/i,
        /[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i,
        /!\[[^\]]*\]\([^)]+\)/,
        /\[[^\]]*\]\([^)]+\)/,
        /```/,
        /<\?php/i,
        /javascript:/i,
        /data:/i,
        /onerror\s*=/i,
        /onclick\s*=/i
      ];

      return !blockedPatterns.some(function (pattern) {
        return pattern.test(text);
      });
    }

    function ensurePlainText(event) {
      let isValid = true;

      plainTextFields.forEach(function (field) {
        let warning = field.parentElement ? field.parentElement.querySelector('.wr-plain-warning') : null;

        if (!warning && field.parentElement) {
          warning = document.createElement('div');
          warning.className = 'wr-plain-warning';
          warning.textContent = 'Only plain text is allowed. Remove links, HTML, code, markdown, emails, or special formatting.';
          field.parentElement.appendChild(warning);
        }

        if (!isPlainTextOnly(field.value)) {
          isValid = false;

          if (warning) {
            warning.classList.add('is-visible');
          }
        } else if (warning) {
          warning.classList.remove('is-visible');
        }
      });

      if (!isValid) {
        event.preventDefault();

        const firstWarning = document.querySelector('.wr-plain-warning.is-visible');

        if (firstWarning) {
          firstWarning.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
          });
        }
      }

      return isValid;
    }

    function createAutoTitleFromComment(comment) {
      const clean = String(comment || '').replace(/\s+/g, ' ').trim();
      const limit = 42;
      const minLength = 15;

      if (clean === '') {
        return '';
      }

      const periodIndex = clean.indexOf('.');

      if (periodIndex >= 0) {
        return clean.substring(0, periodIndex + 1).trim();
      }

      if (clean.length <= limit) {
        return clean;
      }

      let title = clean.substring(0, limit).trim();
      title = title.replace(/[,.!?;:\-–—\s]+$/g, '').trim();

      return title !== '' ? title + '…' : '';
    }

    function handleAutoTitleBeforeSubmit(event) {
      if (!ensurePlainText(event)) {
        return;
      }

      if (!reviewTitle || !reviewComment) {
        return;
      }

      const titleValue = String(reviewTitle.value || '').trim();
      const commentValue = String(reviewComment.value || '').trim();

      if (titleValue === '' && commentValue !== '' && !autoTitleConfirmed) {
        event.preventDefault();

        const generatedTitle = createAutoTitleFromComment(commentValue);
        reviewTitle.value = generatedTitle;

        if (generatedTitle.length < 15) {
          autoTitleConfirmed = false;

          if (autoTitleAlert) {
            autoTitleAlert.textContent = 'Auto title is too short. Please write a longer title with at least 15 characters.';
            autoTitleAlert.classList.add('is-visible');
          }
        } else {
          autoTitleConfirmed = true;

          if (autoTitleAlert) {
            autoTitleAlert.textContent = 'We created a title from your experience. Please check it, then click Submit Review again.';
            autoTitleAlert.classList.add('is-visible');
          }
        }

        reviewTitle.scrollIntoView({
          behavior: 'smooth',
          block: 'center'
        });

        window.setTimeout(function () {
          reviewTitle.focus();
        }, 450);

        return;
      }

      if (reviewTitle && String(reviewTitle.value || '').trim().length < 15) {
        event.preventDefault();

        if (autoTitleAlert) {
          autoTitleAlert.textContent = 'Review title is too short. Please write a longer title with at least 15 characters.';
          autoTitleAlert.classList.add('is-visible');
        }

        reviewTitle.scrollIntoView({
          behavior: 'smooth',
          block: 'center'
        });

        window.setTimeout(function () {
          reviewTitle.focus();
        }, 450);
      }
    }

    function syncMathSubmit() {
      if (!mathAnswer || !mathCorrectAnswer || !submitReviewBtn) {
        return;
      }

      const current = String(mathAnswer.value || '').trim();
      const correct = String(mathCorrectAnswer.value || '').trim();

      if (current !== '' && current === correct) {
        submitReviewBtn.disabled = false;
        submitReviewBtn.classList.remove('wr-submit-disabled');
        submitReviewBtn.textContent = 'Submit Review';

        if (mathStatus) {
          mathStatus.textContent = 'Correct. Submit Review is enabled.';
          mathStatus.className = 'wr-math-status ok';
        }
      } else {
        submitReviewBtn.disabled = true;
        submitReviewBtn.classList.add('wr-submit-disabled');
        submitReviewBtn.textContent = 'Submit Review';

        if (mathStatus) {
          mathStatus.textContent = current === ''
            ? 'Solve the math question to enable Submit Review.'
            : 'Wrong answer. Please try again.';
          mathStatus.className = 'wr-math-status';
        }
      }
    }

    if (anonymous) {
      anonymous.addEventListener('change', syncNameField);
      syncNameField();
    }

    ratingInputs.forEach(function (input) {
      input.addEventListener('change', function () {
        if (input.checked) {
          setReviewCookie(reviewCookieKey, input.value, 180);
        }

        syncRatingCards();
        openReviewDetails();
      });
    });

    ratingCards.forEach(function (card) {
      card.addEventListener('mouseenter', function () {
        const hoverRating = parseInt(card.getAttribute('data-rating') || '0', 10);

        if (!ratingStrip || hoverRating <= 0) {
          return;
        }

        ratingStrip.className = 'wr-rating-strip hover-' + hoverRating;

        ratingCards.forEach(function (item) {
          const itemRating = parseInt(item.getAttribute('data-rating') || '0', 10);
          item.classList.toggle('is-hover-filled', itemRating <= hoverRating);
        });
      });

      card.addEventListener('click', function () {
        const clickedRating = card.getAttribute('data-rating') || '';

        if (/^[1-5]$/.test(clickedRating)) {
          setReviewCookie(reviewCookieKey, clickedRating, 180);
        }

        window.setTimeout(function () {
          syncRatingCards();
          openReviewDetails();
        }, 80);
      });
    });

    if (ratingStrip) {
      ratingStrip.addEventListener('mouseleave', function () {
        ratingCards.forEach(function (item) {
          item.classList.remove('is-hover-filled');
        });

        syncRatingCards();
      });
    }

    syncRatingCards();
  })();
