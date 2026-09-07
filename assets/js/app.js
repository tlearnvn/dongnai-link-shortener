/* ===========================================================================
   Rút gọn link — Phòng GDPT-GDTX Sở GDĐT Đồng Nai
   Thiết kế bởi Trương Anh Tuấn

   JavaScript ở đây chỉ để trang mượt và vui hơn. Mọi chức năng chính đều
   hoạt động khi tắt JavaScript: biểu mẫu gửi bằng POST, biểu đồ vẽ sẵn từ
   máy chủ, mã QR đổi bằng nút "Áp dụng".
   ======================================================================== */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* --------------------------------------------------------------- tiện ích */

    function $(selector, scope) {
        return (scope || document).querySelector(selector);
    }

    function $$(selector, scope) {
        return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
    }

    function on(element, event, handler, options) {
        if (element) {
            element.addEventListener(event, handler, options);
        }
    }

    /** Gộp nhiều lần gọi liên tiếp thành một lần (dùng cho ô nhập). */
    function debounce(fn, wait) {
        var timer = null;
        return function () {
            var args = arguments;
            var self = this;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(self, args);
            }, wait);
        };
    }

    /* ------------------------------------------------------ khay thông báo */

    var toastStack = $('[data-toast-stack]');

    function toast(message, kind) {
        if (!toastStack) {
            return;
        }
        var el = document.createElement('div');
        el.className = 'toast' + (kind ? ' toast--' + kind : '');
        el.textContent = message;
        toastStack.appendChild(el);

        setTimeout(function () {
            el.classList.add('toast--out');
            setTimeout(function () {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            }, 240);
        }, 2600);
    }

    /* --------------------------------------------------- giao diện sáng/tối */

    var THEME_KEY = 'rutgon-theme';

    function currentTheme() {
        var attr = document.documentElement.dataset.theme;
        if (attr === 'light' || attr === 'dark') {
            return attr;
        }
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        document.documentElement.dataset.theme = theme;
        try {
            localStorage.setItem(THEME_KEY, theme);
        } catch (e) {
            /* trình duyệt ở chế độ riêng tư có thể chặn localStorage */
        }
        // Ghi cookie để lần tải trang sau máy chủ vẽ đúng màu ngay từ đầu.
        var secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = 'rutgon_theme=' + theme + '; path=/; max-age=31536000; SameSite=Lax' + secure;
    }

    $$('[data-theme-toggle]').forEach(function (button) {
        on(button, 'click', function () {
            var next = currentTheme() === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            toast(next === 'dark' ? 'Đã chuyển sang giao diện tối 🌙' : 'Đã chuyển sang giao diện sáng ☀️');
        });
    });

    /* ------------------------------------------------- điều hướng & menu */

    var navToggle = $('.nav-toggle');
    var siteNav = $('#dieu-huong');

    on(navToggle, 'click', function () {
        var open = siteNav.classList.toggle('is-open');
        navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    $$('[data-user-menu]').forEach(function (menu) {
        var button = $('.user-menu__button', menu);
        var panel = $('.user-menu__panel', menu);
        if (!button || !panel) {
            return;
        }

        function close() {
            panel.hidden = true;
            button.setAttribute('aria-expanded', 'false');
        }

        on(button, 'click', function (event) {
            event.stopPropagation();
            var willOpen = panel.hidden;
            panel.hidden = !willOpen;
            button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });

        on(document, 'click', function (event) {
            if (!menu.contains(event.target)) {
                close();
            }
        });

        on(document, 'keydown', function (event) {
            if (event.key === 'Escape') {
                close();
            }
        });
    });

    /* ------------------------------------------------------- sao chép nhanh */

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        // Dự phòng cho trang chạy qua http (mạng nội bộ)
        return new Promise(function (resolve, reject) {
            var area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            try {
                document.execCommand('copy') ? resolve() : reject(new Error('copy failed'));
            } catch (error) {
                reject(error);
            } finally {
                document.body.removeChild(area);
            }
        });
    }

    on(document, 'click', function (event) {
        var trigger = event.target.closest('[data-copy]');
        if (!trigger) {
            return;
        }
        event.preventDefault();
        var text = trigger.getAttribute('data-copy');
        copyText(text).then(function () {
            trigger.classList.add('is-copied');
            setTimeout(function () {
                trigger.classList.remove('is-copied');
            }, 340);
            toast('Đã sao chép: ' + (text.length > 44 ? text.slice(0, 44) + '…' : text), 'success');
        }).catch(function () {
            toast('Trình duyệt không cho sao chép tự động. Bạn chọn rồi nhấn Ctrl+C nhé.', 'error');
        });
    });

    // Sao chép cả danh sách liên kết (trang tạo hàng loạt)
    $$('[data-copy-list]').forEach(function (button) {
        on(button, 'click', function () {
            var table = $(button.getAttribute('data-copy-list'));
            if (!table) {
                return;
            }
            var lines = $$('[data-short-url]', table).map(function (link) {
                return link.href;
            });
            if (lines.length === 0) {
                return;
            }
            copyText(lines.join('\n')).then(function () {
                toast('Đã sao chép ' + lines.length + ' liên kết', 'success');
            });
        });
    });

    /* ----------------------------------------------------- đóng thông báo */

    on(document, 'click', function (event) {
        var button = event.target.closest('[data-dismiss]');
        if (!button) {
            return;
        }
        var alertBox = button.closest('.alert');
        if (alertBox) {
            alertBox.style.transition = 'opacity .18s ease, transform .18s ease';
            alertBox.style.opacity = '0';
            alertBox.style.transform = 'translateY(-6px)';
            setTimeout(function () {
                alertBox.remove();
            }, 180);
        }
    });

    /* ------------------------------------------------- hỏi lại trước khi xoá */

    on(document, 'submit', function (event) {
        var form = event.target;
        var message = form.getAttribute && form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    /* ------------------------------------------- đếm số tăng dần cho vui mắt */

    function animateCount(element) {
        var target = parseInt(element.getAttribute('data-count-to'), 10) || 0;
        if (reduceMotion || target === 0) {
            element.textContent = target.toLocaleString('vi-VN');
            return;
        }

        var duration = 900;
        var start = performance.now();

        function step(now) {
            var progress = Math.min(1, (now - start) / duration);
            // Chậm dần ở cuối cho êm mắt
            var eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = Math.round(target * eased).toLocaleString('vi-VN');
            if (progress < 1) {
                requestAnimationFrame(step);
            }
        }

        requestAnimationFrame(step);
    }

    var counters = $$('[data-count-to]');
    if (counters.length > 0) {
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        animateCount(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.4 });
            counters.forEach(function (el) {
                observer.observe(el);
            });
        } else {
            counters.forEach(animateCount);
        }
    }

    /* ------------------------------------------------------------- confetti */

    function confetti() {
        if (reduceMotion) {
            return;
        }
        var colors = ['#6366f1', '#a855f7', '#14b8a6', '#f59e0b', '#ec4899', '#38bdf8'];
        var pieces = 46;

        for (var i = 0; i < pieces; i++) {
            var piece = document.createElement('span');
            piece.className = 'confetti-piece';
            piece.style.left = Math.random() * 100 + 'vw';
            piece.style.background = colors[i % colors.length];
            piece.style.animationDuration = (2.1 + Math.random() * 1.5) + 's';
            piece.style.animationDelay = (Math.random() * 0.35) + 's';
            piece.style.width = (6 + Math.random() * 6) + 'px';
            piece.style.height = (9 + Math.random() * 9) + 'px';
            piece.style.opacity = String(0.75 + Math.random() * 0.25);
            document.body.appendChild(piece);

            (function (node) {
                setTimeout(function () {
                    if (node.parentNode) {
                        node.parentNode.removeChild(node);
                    }
                }, 4200);
            })(piece);
        }
    }

    if ($('[data-celebrate]')) {
        setTimeout(confetti, 220);
    }

    /* ------------------------------------- kiểm tra tên tuỳ chọn còn trống */

    $$('[data-alias-check]').forEach(function (input) {
        var state = $('[data-alias-state]', input.closest('.alias-input'));
        var url = input.getAttribute('data-check-url');
        var currentCode = input.getAttribute('data-alias-current') || '';
        var controller = null;

        function setState(text, kind) {
            if (!state) {
                return;
            }
            state.textContent = text;
            state.className = 'alias-input__state' + (kind ? ' is-' + kind : '');
        }

        var check = debounce(function () {
            var value = input.value.trim();

            if (value === '') {
                setState('', '');
                return;
            }
            if (currentCode !== '' && value.toLowerCase() === currentCode.toLowerCase()) {
                setState('tên hiện tại', '');
                return;
            }
            if (!/^[A-Za-z0-9][A-Za-z0-9._-]*$/.test(value)) {
                setState('có ký tự không dùng được', 'taken');
                return;
            }

            setState('đang kiểm tra…', 'checking');

            if (controller) {
                controller.abort();
            }
            controller = typeof AbortController !== 'undefined' ? new AbortController() : null;

            fetch(url + '?code=' + encodeURIComponent(value), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller ? controller.signal : undefined
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    // Người dùng có thể đã gõ tiếp trong lúc chờ trả lời
                    if (input.value.trim() !== value) {
                        return;
                    }
                    if (data.available) {
                        setState('✓ dùng được', 'free');
                    } else if (data.suggestion) {
                        setState('✗ đã có người dùng', 'taken');
                        suggest(data.suggestion);
                    } else {
                        setState('✗ không dùng được', 'taken');
                    }
                })
                .catch(function () {
                    setState('', '');
                });
        }, 420);

        /** Hiện gợi ý tên thay thế ngay dưới ô nhập. */
        function suggest(name) {
            var field = input.closest('.field');
            if (!field) {
                return;
            }
            var box = $('[data-alias-suggestion]', field);
            if (!box) {
                box = document.createElement('p');
                box.className = 'field__hint';
                box.setAttribute('data-alias-suggestion', '');
                field.appendChild(box);
            }
            box.innerHTML = '';
            box.appendChild(document.createTextNode('Gợi ý còn trống: '));

            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'link-button';
            button.textContent = name;
            on(button, 'click', function () {
                input.value = name;
                input.focus();
                check();
                box.remove();
            });
            box.appendChild(button);
        }

        on(input, 'input', check);
        on(input, 'blur', check);
    });

    // Nút gợi ý tên mẫu ở trang chủ
    $$('[data-alias-suggest]').forEach(function (button) {
        on(button, 'click', function () {
            var input = $('[data-alias-check]');
            if (input) {
                input.value = button.getAttribute('data-alias-suggest');
                input.focus();
                input.dispatchEvent(new Event('input'));
            }
        });
    });

    /* --------------------------------------------------------- mật khẩu */

    $$('[data-toggle-password]').forEach(function (button) {
        on(button, 'click', function () {
            var wrapper = button.closest('.password-field');
            var input = wrapper ? $('input', wrapper) : null;
            if (!input) {
                return;
            }
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.textContent = showing ? '👁️' : '🙈';
        });
    });

    $$('[data-password-meter]').forEach(function (input) {
        var field = input.closest('.field');
        var meter = field ? $('[data-meter]', field) : null;
        if (!meter) {
            return;
        }
        var label = $('.meter__label', meter);

        on(input, 'input', function () {
            var value = input.value;
            if (value === '') {
                meter.hidden = true;
                return;
            }
            meter.hidden = false;

            // Cách tính đơn giản: cộng điểm theo độ dài và sự đa dạng ký tự.
            var score = 0;
            if (value.length >= 8) score++;
            if (value.length >= 12) score++;
            if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
            if (/\d/.test(value)) score++;
            if (/[^A-Za-z0-9]/.test(value)) score++;

            var levels = [
                { text: 'Quá yếu', color: '#dc2626', width: '18%' },
                { text: 'Yếu', color: '#dc2626', width: '34%' },
                { text: 'Tạm được', color: '#d97706', width: '55%' },
                { text: 'Khá', color: '#0284c7', width: '75%' },
                { text: 'Mạnh', color: '#15803d', width: '90%' },
                { text: 'Rất mạnh', color: '#15803d', width: '100%' }
            ];
            var level = levels[Math.min(score, levels.length - 1)];

            meter.style.setProperty('--meter', level.width);
            meter.style.setProperty('--meter-color', level.color);
            if (label) {
                label.textContent = 'Độ mạnh: ' + level.text;
            }
        });
    });

    /* ------------------------------------------------------ tiện ích form */

    $$('[data-set-datetime]').forEach(function (button) {
        on(button, 'click', function () {
            var input = $(button.getAttribute('data-set-datetime'));
            if (input) {
                input.value = button.getAttribute('data-value');
            }
        });
    });

    $$('[data-clear-field]').forEach(function (button) {
        on(button, 'click', function () {
            var input = $(button.getAttribute('data-clear-field'));
            if (input) {
                input.value = '';
            }
        });
    });

    $$('[data-tag-add]').forEach(function (button) {
        on(button, 'click', function () {
            var input = $('#tags');
            if (!input) {
                return;
            }
            var tag = button.getAttribute('data-tag-add');
            var tags = input.value.split(',').map(function (t) {
                return t.trim();
            }).filter(function (t) {
                return t !== '';
            });
            if (tags.indexOf(tag) === -1) {
                tags.push(tag);
                input.value = tags.join(', ');
            }
            input.focus();
        });
    });

    /* -------------------------------------------------- xưởng mã QR (trực tiếp) */

    var qrStudio = $('[data-qr-studio]');
    if (qrStudio) {
        var preview = $('#qr-preview');
        var svgBase = qrStudio.getAttribute('data-svg-base');
        var frame = preview ? preview.closest('.qr-frame') : null;

        function updatePreview() {
            if (!preview) {
                return;
            }
            var data = new FormData(qrStudio);
            var params = new URLSearchParams();

            params.set('co', data.get('co') || '10');
            params.set('le', data.get('le') || '4');
            params.set('mau', data.get('mau') || '#0b1220');
            params.set('nen', data.get('nen') || '#ffffff');
            params.set('sua-loi', data.get('sua-loi') || '1');
            params.set('nguon', data.get('nguon') ? '1' : '0');

            preview.src = svgBase + '?' + params.toString();
            if (frame) {
                frame.style.background = data.get('nen') || '#ffffff';
            }

            // Cập nhật các nút tải về cho khớp tuỳ chọn đang xem
            $$('a[download]', qrStudio.closest('.qr-studio')).forEach(function (link) {
                link.href = link.href.split('?')[0] + '?' + params.toString() + '&tai=1';
            });

            // Kích thước ảnh PNG sẽ xuất ra (số ô của mã do máy chủ cho biết)
            var modules = parseInt(qrStudio.getAttribute('data-modules'), 10) || 25;
            var scale = parseInt(data.get('co'), 10) || 10;
            var border = parseInt(data.get('le'), 10) || 0;
            var pixels = String((modules + border * 2) * scale);
            $$('[data-px-size]').forEach(function (node) {
                node.textContent = pixels;
            });
        }

        $$('output[data-output-for]', qrStudio).forEach(function (output) {
            var input = $('#' + output.getAttribute('data-output-for'), qrStudio);
            if (input) {
                on(input, 'input', function () {
                    output.textContent = input.value;
                });
            }
        });

        $$('input, select', qrStudio).forEach(function (control) {
            on(control, control.type === 'range' || control.type === 'color' ? 'input' : 'change', debounce(updatePreview, 140));
        });

        $$('.swatch').forEach(function (swatch) {
            on(swatch, 'click', function () {
                var dark = $('#mau', qrStudio);
                var light = $('#nen', qrStudio);
                if (dark) dark.value = swatch.getAttribute('data-swatch-dark');
                if (light) light.value = swatch.getAttribute('data-swatch-light');
                updatePreview();
            });
        });
    }

    /* ---------------------------------------------------------- in mã QR */

    $$('[data-print]').forEach(function (button) {
        on(button, 'click', function () {
            var image = $(button.getAttribute('data-print'));
            if (!image) {
                return;
            }
            var win = window.open('', '_blank');
            if (!win) {
                toast('Trình duyệt đã chặn cửa sổ in. Bạn cho phép rồi thử lại nhé.', 'error');
                return;
            }
            win.document.write(
                '<!doctype html><html lang="vi"><head><meta charset="utf-8">' +
                '<title>In mã QR</title><style>' +
                'body{margin:0;display:grid;place-items:center;min-height:100vh;font-family:sans-serif}' +
                'img{width:70mm;height:70mm}p{margin:8mm 0 0;font-size:11pt}' +
                '</style></head><body>' +
                '<div style="text-align:center"><img src="' + image.src + '" alt="Mã QR">' +
                '<p>' + document.title + '</p></div>' +
                '<script>window.onload=function(){window.print();}<\/script>' +
                '</body></html>'
            );
            win.document.close();
        });
    });

    /* ------------------------------------------------------ chia sẻ hệ thống */

    $$('[data-share-url]').forEach(function (button) {
        if (!navigator.share) {
            return;
        }
        button.hidden = false;
        on(button, 'click', function () {
            navigator.share({
                title: button.getAttribute('data-share-title') || document.title,
                url: button.getAttribute('data-share-url')
            }).catch(function () {
                /* người dùng bấm huỷ — không cần báo gì */
            });
        });
    });

    /* --------------------------------------------------- công cụ gắn thẻ UTM */

    var utmForm = $('[data-utm-form]');
    if (utmForm) {
        var result = $('#utm_result', utmForm);

        function buildUtmUrl() {
            var raw = ($('#utm_url', utmForm).value || '').trim();
            if (raw === '') {
                result.value = '';
                return '';
            }
            if (!/^https?:\/\//i.test(raw)) {
                raw = 'https://' + raw.replace(/^\/+/, '');
            }

            var url;
            try {
                url = new URL(raw);
            } catch (error) {
                result.value = 'Địa chỉ chưa hợp lệ.';
                return '';
            }

            ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content'].forEach(function (key) {
                var field = $('#' + key, utmForm);
                var value = field ? field.value.trim() : '';
                if (value !== '') {
                    url.searchParams.set(key, value);
                } else {
                    url.searchParams.delete(key);
                }
            });

            result.value = url.toString();
            return url.toString();
        }

        $$('input', utmForm).forEach(function (input) {
            on(input, 'input', buildUtmUrl);
        });
        on(utmForm, 'reset', function () {
            setTimeout(function () {
                result.value = '';
            }, 0);
        });

        on($('[data-utm-copy]', utmForm), 'click', function () {
            var value = buildUtmUrl();
            if (value === '') {
                toast('Bạn nhập địa chỉ trang đích trước nhé.', 'error');
                return;
            }
            copyText(value).then(function () {
                toast('Đã sao chép liên kết có gắn thẻ', 'success');
            });
        });

        on($('[data-utm-shorten]', utmForm), 'click', function () {
            var value = buildUtmUrl();
            if (value === '') {
                toast('Bạn nhập địa chỉ trang đích trước nhé.', 'error');
                return;
            }
            var base = document.querySelector('.brand').getAttribute('href');
            window.location.href = base + '?url=' + encodeURIComponent(value) + '#rut-gon';
        });
    }

    /* -------------------------------------- điền sẵn địa chỉ từ tham số ?url= */

    (function prefillFromQuery() {
        var params = new URLSearchParams(window.location.search);
        var url = params.get('url');
        var target = $('#target_url');
        if (url && target && target.value === '') {
            target.value = url;
            target.focus();
        }
    })();

    /* ------------------------- chặn bấm hai lần vào nút rút gọn (mạng chậm) */

    var shortenerForm = $('[data-shortener]');
    if (shortenerForm) {
        on(shortenerForm, 'submit', function () {
            var button = $('button[type="submit"]', shortenerForm);
            if (button) {
                button.disabled = true;
                var labelNode = $('span', button);
                if (labelNode) {
                    labelNode.textContent = 'Đang xử lý…';
                }
                // Bật lại nếu trang không chuyển sau 6 giây (mạng chậm hoặc lỗi)
                setTimeout(function () {
                    button.disabled = false;
                    if (labelNode) {
                        labelNode.textContent = 'Rút gọn ngay';
                    }
                }, 6000);
            }
        });
    }
})();
