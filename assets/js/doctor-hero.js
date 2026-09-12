(function () {
    const shareButton = document.querySelector('[data-doctor-share]');

    if (!shareButton) {
        return;
    }

    function readShareJSON(value, fallback) {
        try {
            return JSON.parse(value);
        } catch (error) {
            return fallback;
        }
    }

    /*
     * Share title and copied profile details.
     */
    const shareTitle = readShareJSON(shareButton.dataset.shareTitle, '');

    /*
     * One exact share structure for every social action:
     * Name - Specialty - District
     * Degree
     * Designation
     * Primary Hospital
     * Chamber:-
     * Chamber Name
     * Profile URL
     */
    const profileDetails = readShareJSON(shareButton.dataset.profileDetails, []);

    /*
     * Premium photo-card data. The visual card uses Name, Degree, Specialty, Designation and Primary Hospital in that order.
     */
    const profileCardData = readShareJSON(shareButton.dataset.profileCard, {});

    const profileCardMetadata = readShareJSON(shareButton.dataset.profileCardMeta, {});

    /*
     * Default aria-label/title captured before any mutation, so the copied
     * state can restore them exactly afterward.
     */
    const defaultShareAriaLabel = shareButton.getAttribute('aria-label') || '';
    const defaultShareTitle = shareButton.getAttribute('title') || '';
    const copiedLabel = shareButton.dataset.copiedLabel || 'Copied';

    function fallbackCopyText(text) {
        return new Promise(function (resolve, reject) {
            const textArea = document.createElement('textarea');

            textArea.value = text;
            textArea.setAttribute('readonly', '');
            textArea.setAttribute('aria-hidden', 'true');
            textArea.style.position = 'fixed';
            textArea.style.top = '0';
            textArea.style.left = '-9999px';
            textArea.style.opacity = '0';
            textArea.style.pointerEvents = 'none';

            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                const copied = document.execCommand('copy');
                document.body.removeChild(textArea);

                copied ? resolve() : reject(new Error('Copy failed'));
            } catch (error) {
                document.body.removeChild(textArea);
                reject(error);
            }
        });
    }

    function buildExactShareMessage(payload) {
        return [payload.text, payload.url].filter(Boolean).join('\n');
    }

    function copyShareText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).catch(function () {
                return fallbackCopyText(text);
            });
        }

        return fallbackCopyText(text);
    }

    function showCopiedState() {
        shareButton.classList.add('is-copied');

        shareButton.setAttribute('aria-label', copiedLabel);
        shareButton.setAttribute('title', copiedLabel);

        window.setTimeout(function () {
            shareButton.classList.remove('is-copied');
            shareButton.setAttribute('aria-label', defaultShareAriaLabel);
            shareButton.setAttribute('title', defaultShareTitle);
        }, 1400);
    }

    function roundedRect(context, x, y, width, height, radius) {
        const safeRadius = Math.min(radius, width / 2, height / 2);

        context.beginPath();
        context.moveTo(x + safeRadius, y);
        context.arcTo(x + width, y, x + width, y + height, safeRadius);
        context.arcTo(x + width, y + height, x, y + height, safeRadius);
        context.arcTo(x, y + height, x, y, safeRadius);
        context.arcTo(x, y, x + width, y, safeRadius);
        context.arcTo(x, y, x + width, y, safeRadius);
        context.closePath();
    }

    function getInitials(name) {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);

        if (!parts.length) {
            return 'D';
        }

        return parts.slice(0, 2).map(function (part) {
            return part.charAt(0);
        }).join('').toUpperCase();
    }

    function wrapText(context, text, maxWidth, maxLines) {
        const words = String(text || '').trim().split(/\s+/).filter(Boolean);
        const lines = [];
        let line = '';

        words.forEach(function (word) {
            const nextLine = line ? line + ' ' + word : word;

            if (context.measureText(nextLine).width <= maxWidth || line === '') {
                line = nextLine;
                return;
            }

            lines.push(line);
            line = word;
        });

        if (line) {
            lines.push(line);
        }

        if (lines.length > maxLines) {
            const clipped = lines.slice(0, maxLines);
            let lastLine = clipped[maxLines - 1];

            while (lastLine.length > 1 && context.measureText(lastLine + '…').width > maxWidth) {
                lastLine = lastLine.slice(0, -1);
            }

            clipped[maxLines - 1] = lastLine + '…';
            return clipped;
        }

        return lines;
    }

    function drawWrappedText(context, text, x, y, maxWidth, lineHeight, maxLines) {
        const lines = wrapText(context, text, maxWidth, maxLines);

        lines.forEach(function (line, index) {
            context.fillText(line, x, y + (index * lineHeight));
        });

        return y + (lines.length * lineHeight);
    }

    function ellipsizeText(context, text, maxWidth) {
        let value = String(text || '').trim();

        if (context.measureText(value).width <= maxWidth) {
            return value;
        }

        while (value.length > 1 && context.measureText(value + '…').width > maxWidth) {
            value = value.slice(0, -1);
        }

        return value + '…';
    }

    function drawProfileImage(context, image, x, y, width, height, radius) {
        roundedRect(context, x, y, width, height, radius);
        context.save();
        context.clip();

        const imageRatio = image.naturalWidth / image.naturalHeight;
        const boxRatio = width / height;
        let drawWidth = width;
        let drawHeight = height;
        let drawX = x;
        let drawY = y;

        if (imageRatio > boxRatio) {
            drawWidth = height * imageRatio;
            drawX = x - ((drawWidth - width) / 2);
        } else {
            drawHeight = width / imageRatio;
            drawY = y - ((drawHeight - height) / 2);
        }

        context.drawImage(image, drawX, drawY, drawWidth, drawHeight);
        context.restore();
    }

    function drawPhotoPlaceholder(context, x, y, width, height, initials) {
        const gradient = context.createLinearGradient(x, y, x + width, y + height);
        gradient.addColorStop(0, '#8fc6ff');
        gradient.addColorStop(1, '#3b8fe7');

        roundedRect(context, x, y, width, height, 26);
        context.fillStyle = gradient;
        context.fill();

        context.fillStyle = '#ffffff';
        context.font = '700 86px "Segoe UI", "Noto Sans Bengali", Arial, sans-serif';
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillText(initials, x + (width / 2), y + (height / 2));
        context.textAlign = 'left';
        context.textBaseline = 'alphabetic';
    }

    function drawMedicalMark(context, x, y, size) {
        context.save();
        context.fillStyle = 'rgba(47, 143, 240, 0.12)';
        context.beginPath();
        context.arc(x, y, size / 2, 0, Math.PI * 2);
        context.fill();

        context.strokeStyle = '#2f8ff0';
        context.lineWidth = 5;
        context.lineCap = 'round';
        context.beginPath();
        context.moveTo(x - (size * 0.18), y);
        context.lineTo(x + (size * 0.18), y);
        context.moveTo(x, y - (size * 0.18));
        context.lineTo(x, y + (size * 0.18));
        context.stroke();
        context.restore();
    }

    function drawDecorativePattern(context, width, height) {
        context.save();

        context.strokeStyle = 'rgba(47, 143, 240, 0.11)';
        context.lineWidth = 2;

        for (let index = 0; index < 7; index += 1) {
            const x = 760 + (index * 110);
            context.beginPath();
            context.arc(x, 95, 85 + (index * 12), Math.PI * 1.08, Math.PI * 1.78);
            context.stroke();
        }

        context.fillStyle = 'rgba(47, 143, 240, 0.10)';
        context.beginPath();
        context.arc(width - 70, height - 45, 210, 0, Math.PI * 2);
        context.fill();

        context.fillStyle = 'rgba(91, 166, 237, 0.07)';
        context.beginPath();
        context.arc(70, height - 80, 175, 0, Math.PI * 2);
        context.fill();

        context.restore();
    }

    function drawPhotoCardValue(context, value, x, y, maxWidth, lineHeight, maxLines, color, font) {
        value = String(value || '').trim();

        if (!value) {
            return y;
        }

        context.fillStyle = color;
        context.font = font;

        return drawWrappedText(
            context,
            value,
            x,
            y,
            maxWidth,
            lineHeight,
            maxLines
        );
    }

    function createProfileCardFileName(title) {
        const fallbackName = 'doctor-profile-card';
        let fileName = String(title || '').trim();

        /*
         * Example:
         * Dr. Ahmed Hasan - Cardiology - Dhaka.jpg
         */
        fileName = fileName
            .replace(/[\\/:*?"<>|]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .replace(/[. ]+$/g, '');

        return (fileName || fallbackName) + '.jpg';
    }

    function dataUrlToFile(dataUrl, fileName, mimeType) {
        const base64 = dataUrl.split(',')[1];

        if (!base64) {
            return null;
        }

        const binary = window.atob(base64);
        const bytes = new Uint8Array(binary.length);

        for (let index = 0; index < binary.length; index += 1) {
            bytes[index] = binary.charCodeAt(index);
        }

        return new File(
            [new Blob([bytes], { type: mimeType })],
            fileName,
            { type: mimeType }
        );
    }

    function fileToDataUrl(file) {
        return new Promise(function (resolve, reject) {
            const reader = new FileReader();

            reader.onload = function () {
                resolve(String(reader.result || ''));
            };

            reader.onerror = function () {
                reject(new Error('Unable to read the photo card.'));
            };

            reader.readAsDataURL(file);
        });
    }

    function appendFormField(form, name, value) {
        const field = document.createElement('input');

        field.type = 'hidden';
        field.name = name;
        field.value = String(value ?? '');

        form.appendChild(field);
    }

    function updatePublicCardMeta(cardUrl) {
        if (!cardUrl) {
            return;
        }

        [
            ['meta[property="og:image"]', 'content'],
            ['meta[property="og:image:secure_url"]', 'content'],
            ['meta[name="twitter:image"]', 'content']
        ].forEach(function (item) {
            const meta = document.querySelector(item[0]);

            if (meta) {
                meta.setAttribute(item[1], cardUrl);
            }
        });
    }

    function saveCardForSocialPreview(file) {
        if (!file || !profileCardMetadata.endpoint) {
            return Promise.resolve(null);
        }

        /*
         * The public JPG is saved before an icon opens its social network.
         * This keeps the generated social-preview image current.
         */
        return fileToDataUrl(file).then(function (cardImageData) {
            const payload = new URLSearchParams();

            payload.set('action', 'save');
            payload.set('lang', profileCardMetadata.lang || 'en');
            payload.set('card_image', cardImageData);
            payload.set('title', profileCardMetadata.title);
            payload.set('filename', profileCardMetadata.filename);
            payload.set('subject', profileCardMetadata.subject);
            payload.set('tags', profileCardMetadata.tags);
            payload.set('author', profileCardMetadata.author);
            payload.set('rating', profileCardMetadata.rating);

            return fetch(profileCardMetadata.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                },
                body: payload.toString(),
                credentials: 'same-origin'
            });
        }).then(function (response) {
            if (!response || !response.ok) {
                return null;
            }

            return response.json().catch(function () {
                return null;
            });
        }).then(function (result) {
            if (result && result.success && result.url) {
                updatePublicCardMeta(result.url);
            }

            return result;
        }).catch(function () {
            /* Upload failure does not block sharing the profile URL. */
            return null;
        });
    }

    function createProfileCardFile() {
        try {
            const canvas = document.createElement('canvas');
            const width = 1200;
            const height = 630;
            const context = canvas.getContext('2d');

            if (!context) {
                return null;
            }

            canvas.width = width;
            canvas.height = height;

            /*
             * Premium navy-blue medical profile card.
             * This card is generated for public social preview and sharing.
             */
            const cardBackground = context.createLinearGradient(0, 0, width, height);
            cardBackground.addColorStop(0, '#f8fbff');
            cardBackground.addColorStop(0.48, '#eef7ff');
            cardBackground.addColorStop(1, '#d9edff');
            context.fillStyle = cardBackground;
            context.fillRect(0, 0, width, height);

            drawDecorativePattern(context, width, height);

            context.save();
            context.shadowColor = 'rgba(38, 90, 145, 0.14)';
            context.shadowBlur = 26;
            context.shadowOffsetY = 13;
            context.fillStyle = 'rgba(255, 255, 255, 0.97)';
            roundedRect(context, 30, 30, 1140, 570, 32);
            context.fill();
            context.restore();

            context.strokeStyle = 'rgba(110, 171, 231, 0.34)';
            context.lineWidth = 2;
            roundedRect(context, 30, 30, 1140, 570, 32);
            context.stroke();

            const leftPanel = context.createLinearGradient(30, 30, 376, 600);
            leftPanel.addColorStop(0, 'rgba(232, 245, 255, 0.98)');
            leftPanel.addColorStop(1, 'rgba(207, 232, 255, 0.98)');
            context.fillStyle = leftPanel;
            roundedRect(context, 30, 30, 350, 570, 32);
            context.fill();

            context.fillStyle = '#2f8ff0';
            roundedRect(context, 30, 30, 350, 10, 10);
            context.fill();

            context.fillStyle = '#1f5f9d';
            context.font = '700 24px "Segoe UI", "Noto Sans Bengali", Arial, sans-serif';
            context.fillText(profileCardData.siteName || 'MedicBD', 74, 88);

            context.fillStyle = '#6387aa';
            context.font = '500 15px "Segoe UI", "Noto Sans Bengali", Arial, sans-serif';
            context.fillText(profileCardData.profileLabel || 'Doctor Profile', 75, 116);

            drawMedicalMark(context, 323, 92, 54);

            const photoX = 74;
            const photoY = 151;
            const photoWidth = 262;
            const photoHeight = 326;
            const documentPhoto = document.querySelector('.medic-doctor-photo');

            context.save();
            context.shadowColor = 'rgba(36, 97, 157, 0.16)';
            context.shadowBlur = 18;
            context.shadowOffsetY = 9;
            context.fillStyle = '#ffffff';
            roundedRect(context, photoX - 7, photoY - 7, photoWidth + 14, photoHeight + 14, 30);
            context.fill();
            context.restore();

            let photoDrawn = false;

            try {
                const photoUrl = documentPhoto
                    ? new URL(documentPhoto.currentSrc || documentPhoto.src, window.location.href)
                    : null;

                const isSafePhoto = photoUrl && (
                    photoUrl.origin === window.location.origin ||
                    photoUrl.protocol === 'data:'
                );

                if (
                    documentPhoto &&
                    documentPhoto.complete &&
                    documentPhoto.naturalWidth > 0 &&
                    isSafePhoto
                ) {
                    drawProfileImage(context, documentPhoto, photoX, photoY, photoWidth, photoHeight, 24);
                    photoDrawn = true;
                }
            } catch (error) {
                photoDrawn = false;
            }

            if (!photoDrawn) {
                drawPhotoPlaceholder(
                    context,
                    photoX,
                    photoY,
                    photoWidth,
                    photoHeight,
                    getInitials(profileCardData.name)
                );
            }

            context.fillStyle = '#6387aa';
            context.font = '500 15px "Segoe UI", "Noto Sans Bengali", Arial, sans-serif';
            context.fillText('MEDICBD', 75, 530);

            context.fillStyle = '#234566';
            context.font = '700 19px "Segoe UI", "Noto Sans Bengali", Arial, sans-serif';
            drawWrappedText(context, profileCardData.name, 75, 560, 260, 25, 2);

            const mainX = 431;
            const mainWidth = 674;

            context.fillStyle = '#2f8ff0';
            context.font = '600 16px "Segoe UI", "Noto Sans Bengali", Arial, sans-serif';
            context.fillText(profileCardData.profileLabel || 'Doctor Profile', mainX, 92);

            /*
             * Fixed value-only photo-card order:
             * Name
             * Degree
             * [blank space]
             * Specialty
             * [blank space]
             * Designation
             * Primary Hospital
             *
             * The public card intentionally has no field labels, no dividers,
             * and no chamber row. The footer still shows the profile URL.
             */
            context.fillStyle = '#1b3855';
            context.font = '700 34px "Segoe UI", "Noto Sans Bengali", Arial, sans-serif';
            const nameEndY = drawWrappedText(
                context,
                profileCardData.name,
                mainX,
                148,
                mainWidth,
                43,
                2
            );

            context.fillStyle = '#2f8ff0';
            roundedRect(context, mainX, nameEndY + 12, 92, 5, 3);
            context.fill();

            let contentY = Math.max(248, nameEndY + 54);

            contentY = drawPhotoCardValue(
                context,
                profileCardData.degree,
                mainX,
                contentY,
                mainWidth,
                27,
                2,
                '#294862',
                '500 21px "Segoe UI", "Noto Sans Bengali", Arial, sans-serif'
            );

            /* Blank line after Degree. */
            contentY += 22;

            contentY = drawPhotoCardValue(
                context,
                profileCardData.specialty,
                mainX,
                contentY,
                mainWidth,
                29,
                2,
                '#2f8ff0',
                '700 22px "Segoe UI", "Noto Sans Bengali", Arial, sans-serif'
            );

            /* Blank line after Specialty. */
            contentY += 28;

            contentY = drawPhotoCardValue(
                context,
                profileCardData.designation,
                mainX,
                contentY,
                mainWidth,
                26,
                2,
                '#294862',
                '500 20px "Segoe UI", "Noto Sans Bengali", Arial, sans-serif'
            );

            contentY += 10;

            drawPhotoCardValue(
                context,
                profileCardData.hospital,
                mainX,
                contentY,
                mainWidth,
                26,
                2,
                '#294862',
                '500 20px "Segoe UI", "Noto Sans Bengali", Arial, sans-serif'
            );

            const footerY = 540;
            context.fillStyle = '#f2f8ff';
            roundedRect(context, mainX, footerY, mainWidth, 40, 12);
            context.fill();

            context.strokeStyle = '#dbe9f6';
            context.lineWidth = 1;
            roundedRect(context, mainX, footerY, mainWidth, 40, 12);
            context.stroke();

            const profileUrl = window.location.origin + window.location.pathname;

            context.fillStyle = '#47759f';
            context.font = '500 15px "Segoe UI", Arial, sans-serif';
            context.fillText(ellipsizeText(context, profileUrl, mainWidth - 38), mainX + 18, footerY + 26);

            /*
             * JPG provides broad compatibility for Windows details, social apps
             * and public social-preview images.
             */
            const cardFileName = createProfileCardFileName(profileCardData.title);
            let dataUrl = '';

            try {
                dataUrl = canvas.toDataURL('image/jpeg', 0.94);
            } catch (error) {
                return null;
            }

            if (!dataUrl || !dataUrl.startsWith('data:image/jpeg')) {
                return null;
            }

            return dataUrlToFile(dataUrl, cardFileName, 'image/jpeg');
        } catch (error) {
            return null;
        }
    }

    const socialShareMenu = document.querySelector('[data-social-share-menu]');
    const socialShareClose = document.querySelector('[data-social-share-close]');
    const socialShareItems = socialShareMenu
        ? Array.prototype.slice.call(socialShareMenu.querySelectorAll('[data-share-network]'))
        : [];

    let activeSharePayload = null;
    let activeCardSavePromise = Promise.resolve(null);
    let socialShareIsOpening = false;

    function closeSocialShareMenu() {
        if (!socialShareMenu) {
            return;
        }

        socialShareMenu.hidden = true;
        socialShareMenu.style.display = '';
        shareButton.setAttribute('aria-expanded', 'false');
    }

    function openSocialShareMenu() {
        if (!socialShareMenu) {
            return;
        }

        socialShareMenu.hidden = false;
        socialShareMenu.style.display = 'block';
        shareButton.setAttribute('aria-expanded', 'true');
    }

    function buildSocialUrls(payload) {
        const url = encodeURIComponent(payload.url);
        const exactText = String(payload.text || '').trim();
        const exactMessage = [exactText, payload.url].filter(Boolean).join('\n');

        const encodedText = encodeURIComponent(exactText);
        const encodedMessage = encodeURIComponent(exactMessage);

        return {
            /*
             * Facebook reads the URL for the 1200x630 OG photo card and the
             * quote parameter for the formatted doctor details. Facebook can
             * still let the user edit or omit the quote in its own composer.
             */
            facebook: 'https://www.facebook.com/sharer/sharer.php?u=' + url + '&quote=' + encodedText,

            /*
             * These destinations receive the exact same doctor details text
             * and final profile URL that Copy Link puts on the clipboard.
             * The JPG is not uploaded or attached by this code.
             */
            whatsapp: 'https://api.whatsapp.com/send?text=' + encodedMessage,
            telegram: 'https://t.me/share/url?url=' + url + '&text=' + encodedText,
            twitter: 'https://twitter.com/intent/tweet?text=' + encodedMessage,

            /*
             * LinkedIn builds its post preview from the profile URL. LinkedIn
             * does not provide a reliable browser parameter for prefilled
             * post text, but it will use the same OG title/card.
             */
            linkedin: 'https://www.linkedin.com/sharing/share-offsite/?url=' + url
        };
    }

    function prepareSocialShareItems(payload) {
        const urls = buildSocialUrls(payload);

        socialShareItems.forEach(function (item) {
            const network = item.getAttribute('data-share-network') || '';

            /*
             * Keep share URLs out of href attributes. This prevents the
             * browser from opening a second tab through default anchor action.
             */
            if (urls[network]) {
                item.setAttribute('data-share-url', urls[network]);
                item.setAttribute('href', '#');
                item.removeAttribute('target');
                item.removeAttribute('rel');
            }
        });
    }

    function openSharePopupImmediately() {
        /*
         * This opens one blank popup during the direct user click. The popup
         * is then redirected after the photo-card save request finishes.
         * It prevents duplicate tabs and avoids popup-blocker timing issues.
         */
        const popup = window.open(
            '',
            'medic_doctor_share',
            'width=720,height=620,resizable=yes,scrollbars=yes'
        );

        if (!popup) {
            return null;
        }

        try {
            popup.document.title = 'Preparing share...';
            popup.document.body.innerHTML =
                '<p style="font-family:Arial,sans-serif;padding:24px;color:#333;">Preparing share...</p>';
        } catch (error) {
            /* The popup can still be redirected even if the loading text fails. */
        }

        return popup;
    }

    function openNetworkShare(popup, url) {
        if (!url) {
            return;
        }

        if (popup && !popup.closed) {
            popup.location.replace(url);
            return;
        }

        /*
         * Only used if the browser blocks popups. It opens one destination in
         * the current page instead of creating a duplicate tab.
         */
        window.location.assign(url);
    }

    function isMobileShareDevice() {
        const userAgent = navigator.userAgent || '';

        return /Android|webOS|iPhone|iPad|iPod|IEMobile|Opera Mini/i.test(userAgent)
            || (window.matchMedia && window.matchMedia('(max-width: 767px)').matches);
    }

    function shareMobileTextLinkAndPhoto(payload) {
        if (!payload || !navigator.share) {
            return false;
        }

        /*
         * Mobile native app share:
         * 1. Exact doctor details text
         * 2. Profile link
         * 3. Generated JPG Photo Card
         *
         * The JPG is attached only when Web Share file support is available.
         * Otherwise, sharing continues with the same text + profile link.
         */
        const nativeShareData = {
            title: payload.title,
            text: payload.text,
            url: payload.url
        };

        const cardFile = payload.cardFile || null;
        const canAttachCard = cardFile
            && typeof navigator.canShare === 'function'
            && navigator.canShare({ files: [cardFile] });

        if (canAttachCard) {
            nativeShareData.files = [cardFile];
        }

        navigator.share(nativeShareData).catch(function () {
            /*
             * Closing the native share sheet is normal. A user can open it
             * again by tapping Share.
             */
        });

        return true;
    }

    function shareMore(payload) {
        if (!navigator.share) {
            copyShareText(buildExactShareMessage(payload))
                .then(showCopiedState)
                .catch(function () {});
            return;
        }

        const nativeShareData = {
            title: payload.title,
            text: payload.text,
            url: payload.url
        };

        const cardFile = payload.cardFile || null;

        if (
            cardFile
            && typeof navigator.canShare === 'function'
            && navigator.canShare({ files: [cardFile] })
        ) {
            nativeShareData.files = [cardFile];
        }

        navigator.share(nativeShareData).catch(function () {
            /* Closing the native share sheet is normal. */
        });
    }

    shareButton.setAttribute('aria-haspopup', 'true');
    shareButton.setAttribute('aria-expanded', 'false');

    shareButton.addEventListener('click', function () {
        const currentUrl = window.location.origin + window.location.pathname;
        const shareText = [shareTitle].concat(profileDetails).join('\n');
        const cardFile = createProfileCardFile();

        activeSharePayload = {
            title: shareTitle,
            text: shareText,
            url: currentUrl,
            cardFile: cardFile
        };

        /*
         * Every Share-button click keeps the previous automatic copy behavior:
         * exact doctor details + profile URL are copied on mobile and desktop.
         */
        copyShareText(buildExactShareMessage(activeSharePayload))
            .then(showCopiedState)
            .catch(function () {
                /* Sharing remains available when clipboard access is blocked. */
            });

        /*
         * Save/overwrite the public card for OG/link-preview use. On mobile,
         * the locally generated JPG is also supplied to native app sharing
         * when the browser and selected app accept file attachments.
         */
        activeCardSavePromise = cardFile
            ? saveCardForSocialPreview(cardFile)
            : Promise.resolve(null);

        /*
         * Mobile: open the device app chooser with text + profile URL + JPG
         * Photo Card when native file sharing is supported.
         * Desktop: open the six-option social dropdown.
         */
        if (isMobileShareDevice() && shareMobileTextLinkAndPhoto(activeSharePayload)) {
            return;
        }

        prepareSocialShareItems(activeSharePayload);
        openSocialShareMenu();
    });

    socialShareItems.forEach(function (item) {
        item.addEventListener('click', function (event) {
            const network = item.getAttribute('data-share-network') || '';

            if (!activeSharePayload) {
                event.preventDefault();
                return;
            }

            if (network === 'copy') {
                event.preventDefault();
                copyShareText(buildExactShareMessage(activeSharePayload))
                    .then(function () {
                        showCopiedState();
                        closeSocialShareMenu();
                    })
                    .catch(function () {});
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            if (socialShareIsOpening) {
                return;
            }

            const targetUrl = item.getAttribute('data-share-url') || '';

            if (!targetUrl) {
                return;
            }

            socialShareIsOpening = true;

            /*
             * Open exactly one blank popup during the user click. After the
             * newest JPG card is saved, only that popup navigates to the
             * selected social platform.
             */
            const sharePopup = openSharePopupImmediately();
            closeSocialShareMenu();

            activeCardSavePromise.finally(function () {
                socialShareIsOpening = false;
                openNetworkShare(sharePopup, targetUrl);
            });
        });
    });

    document.addEventListener('click', function (event) {
        if (!socialShareMenu || socialShareMenu.hidden) {
            return;
        }

        if (!socialShareMenu.contains(event.target) && !shareButton.contains(event.target)) {
            closeSocialShareMenu();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSocialShareMenu();
        }
    });
}());
