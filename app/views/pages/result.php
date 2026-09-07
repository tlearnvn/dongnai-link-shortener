<?php
/**
 * Trang kết quả sau khi rút gọn.
 *
 * @var array<string, mixed> $link
 * @var bool                 $justCreated
 * @var bool                 $canManage
 */
$code = (string) $link['code'];
$shortUrl = short_url($code);
$qrSvg = url('ma-qr/' . rawurlencode($code) . '.svg');
$state = LinkService::state($link);
?>

<section class="section section--tight">
    <div class="wrap wrap--narrow">
        <div class="card card--glass result"<?= $justCreated ? ' data-celebrate' : '' ?>>
            <?php if ($justCreated): ?>
                <p class="result__congrats">🎉 Xong rồi! Liên kết của bạn đã sẵn sàng.</p>
            <?php else: ?>
                <p class="result__congrats result__congrats--calm">🔗 Thông tin liên kết rút gọn</p>
            <?php endif; ?>

            <div class="result__link">
                <a class="result__url" href="<?= e($shortUrl) ?>" target="_blank" rel="noopener">
                    <?= e(short_url_display($code)) ?>
                </a>
                <div class="result__buttons">
                    <button class="btn btn--primary" type="button" data-copy="<?= e($shortUrl) ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="9" y="9" width="11" height="11" rx="2.5"/>
                            <path d="M15.5 6.5V5.4A2.4 2.4 0 0 0 13.1 3H5.4A2.4 2.4 0 0 0 3 5.4v7.7A2.4 2.4 0 0 0 5.4 15.5h1.1"/>
                        </svg>
                        <span>Sao chép</span>
                    </button>
                    <a class="btn btn--ghost" href="<?= e($shortUrl) ?>" target="_blank" rel="noopener">Mở thử</a>
                </div>
            </div>

            <div class="result__body">
                <div class="result__qr">
                    <div class="qr-frame">
                        <img src="<?= e($qrSvg) ?>" alt="Mã QR của liên kết <?= e($code) ?>" width="220" height="220">
                    </div>
                    <div class="result__qr-actions">
                        <a class="btn btn--ghost btn--sm" href="<?= e($qrSvg . '?tai=1') ?>" download>Tải SVG</a>
                        <a class="btn btn--ghost btn--sm"
                           href="<?= e(url('ma-qr/' . rawurlencode($code) . '.png?co=14&tai=1')) ?>" download>Tải PNG</a>
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('/ma-qr/' . rawurlencode($code))) ?>">Tuỳ chỉnh</a>
                    </div>
                </div>

                <dl class="result__details">
                    <div>
                        <dt>Địa chỉ gốc</dt>
                        <dd>
                            <a href="<?= e((string) $link['target_url']) ?>" target="_blank" rel="noopener nofollow">
                                <?= e(truncate_str((string) $link['target_url'], 90)) ?>
                            </a>
                        </dd>
                    </div>
                    <?php if (!empty($link['title'])): ?>
                        <div>
                            <dt>Tiêu đề</dt>
                            <dd><?= e((string) $link['title']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <div>
                        <dt>Trạng thái</dt>
                        <dd><span class="badge badge--<?= e($state['tone']) ?>"><?= e($state['label']) ?></span></dd>
                    </div>
                    <div>
                        <dt>Tạo lúc</dt>
                        <dd><?= e(Clock::formatShort((string) $link['created_at'])) ?> <small>(GMT+7)</small></dd>
                    </div>
                    <?php if ($link['expires_at'] !== null): ?>
                        <div>
                            <dt>Hết hạn</dt>
                            <dd><?= e(Clock::formatShort((string) $link['expires_at'])) ?> <small>(GMT+7)</small></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($link['max_clicks'] !== null): ?>
                        <div>
                            <dt>Giới hạn lượt nhấp</dt>
                            <dd><?= e(n((int) $link['click_count'])) ?> / <?= e(n((int) $link['max_clicks'])) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($link['password_hash'] !== null): ?>
                        <div>
                            <dt>Bảo vệ</dt>
                            <dd>🔒 Cần nhập mật khẩu mới mở được</dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>

            <div class="result__share">
                <span class="result__share-label">Chia sẻ nhanh:</span>
                <a class="share-btn share-btn--zalo"
                   href="https://zalo.me/share?u=<?= rawurlencode($shortUrl) ?>"
                   target="_blank" rel="noopener noreferrer">Zalo</a>
                <a class="share-btn share-btn--fb"
                   href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($shortUrl) ?>"
                   target="_blank" rel="noopener noreferrer">Facebook</a>
                <a class="share-btn share-btn--mail"
                   href="mailto:?subject=<?= rawurlencode((string) ($link['title'] ?? 'Liên kết')) ?>&amp;body=<?= rawurlencode($shortUrl) ?>">
                    Email
                </a>
                <button class="share-btn share-btn--native" type="button"
                        data-share-url="<?= e($shortUrl) ?>"
                        data-share-title="<?= e((string) ($link['title'] ?? 'Liên kết rút gọn')) ?>" hidden>
                    Chia sẻ…
                </button>
            </div>

            <div class="result__next">
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/#rut-gon')) ?>">Rút gọn liên kết khác</a>
                <?php if ($canManage): ?>
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/thong-ke/' . (int) $link['id'])) ?>">Xem thống kê</a>
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/lien-ket/' . (int) $link['id'] . '/sua')) ?>">Sửa liên kết</a>
                <?php elseif (Auth::user() === null): ?>
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/dang-ky')) ?>">Tạo tài khoản để xem thống kê</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
