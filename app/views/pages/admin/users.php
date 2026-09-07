<?php
/**
 * Quản lý người dùng.
 *
 * @var array<string, mixed> $user   Quản trị viên đang đăng nhập
 * @var array<int, array>    $users
 * @var string               $q
 */
$adminReset = $_SESSION['_admin_reset'] ?? null;
unset($_SESSION['_admin_reset']);
?>
<section class="section">
    <div class="wrap">
        <header class="page-head">
            <div>
                <p class="page-head__eyebrow">Quản trị</p>
                <h1 class="page-head__title">Người dùng</h1>
                <p class="page-head__sub"><?= e(n(count($users))) ?> tài khoản</p>
            </div>
            <div class="page-head__actions">
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/quan-tri')) ?>">← Tổng quan</a>
            </div>
        </header>

        <?php if ($adminReset !== null): ?>
            <div class="callout callout--warning">
                <h2>Mật khẩu tạm cho <?= e((string) $adminReset['username']) ?></h2>
                <p>Chuyển mật khẩu này cho người dùng và nhắc họ đổi lại sau khi đăng nhập:</p>
                <p class="code-box code-box--big">
                    <code><?= e((string) $adminReset['password']) ?></code>
                    <button class="btn btn--primary btn--sm" type="button"
                            data-copy="<?= e((string) $adminReset['password']) ?>">Sao chép</button>
                </p>
            </div>
        <?php endif; ?>

        <form class="filters" method="get" action="<?= e(url('/quan-tri/nguoi-dung')) ?>">
            <div class="filters__search">
                <svg class="filters__icon" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="11" cy="11" r="6.4"/><path d="M20 20l-4.2-4.2"/>
                </svg>
                <input type="search" name="q" value="<?= e($q) ?>"
                       placeholder="Tìm theo tên đăng nhập, họ tên, email…" aria-label="Tìm người dùng">
            </div>
            <div class="filters__buttons">
                <button class="btn btn--primary btn--sm" type="submit">Tìm</button>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/quan-tri/nguoi-dung')) ?>">Bỏ lọc</a>
            </div>
        </form>

        <div class="user-list">
            <?php foreach ($users as $row):
                $isSelf = (int) $row['id'] === (int) $user['id'];
                $isSuspended = (string) $row['status'] !== 'active';
                $action = url('/quan-tri/nguoi-dung/' . (int) $row['id']);
            ?>
                <article class="card user-row<?= $isSuspended ? ' user-row--suspended' : '' ?>">
                    <div class="user-row__main">
                        <span class="avatar avatar--lg" aria-hidden="true">
                            <?= e(mb_strtoupper(mb_substr((string) ($row['full_name'] ?? $row['username']), 0, 1))) ?>
                        </span>
                        <div class="user-row__identity">
                            <h2 class="user-row__name">
                                <?= e((string) ($row['full_name'] ?? $row['username'])) ?>
                                <?php if ((string) $row['role'] === 'admin'): ?>
                                    <span class="badge badge--admin">quản trị</span>
                                <?php endif; ?>
                                <?php if ($isSuspended): ?>
                                    <span class="badge badge--danger">tạm ngưng</span>
                                <?php endif; ?>
                                <?php if ($isSelf): ?>
                                    <span class="badge badge--info">bạn</span>
                                <?php endif; ?>
                            </h2>
                            <p class="user-row__meta">
                                @<?= e((string) $row['username']) ?>
                                <?php if (!empty($row['email'])): ?> · <?= e((string) $row['email']) ?><?php endif; ?>
                                <?php if (!empty($row['unit'])): ?> · <?= e(truncate_str((string) $row['unit'], 40)) ?><?php endif; ?>
                            </p>
                            <p class="user-row__meta user-row__meta--dim">
                                Tham gia <?= e(Clock::formatDate((string) $row['created_at'])) ?> ·
                                <?= e(n((int) $row['link_count'])) ?> liên kết ·
                                <?= e(n((int) $row['click_count'])) ?> lượt nhấp ·
                                đăng nhập lần cuối
                                <?= e($row['last_login_at'] !== null ? Clock::human((string) $row['last_login_at']) : 'chưa bao giờ') ?>
                                <?php if ((int) $row['link_quota'] > 0): ?>
                                    · hạn mức <?= e(n((int) $row['link_quota'])) ?> liên kết
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <div class="user-row__actions">
                        <?php if ((string) $row['role'] === 'admin'): ?>
                            <form method="post" action="<?= e($action) ?>"
                                  data-confirm="Chuyển <?= e((string) $row['username']) ?> về người dùng thường?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="demote">
                                <button class="btn btn--ghost btn--xs" type="submit" <?= $isSelf ? 'disabled' : '' ?>>
                                    Bỏ quyền quản trị
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e($action) ?>"
                                  data-confirm="Cấp quyền quản trị cho <?= e((string) $row['username']) ?>?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="promote">
                                <button class="btn btn--ghost btn--xs" type="submit">Cấp quyền quản trị</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($isSuspended): ?>
                            <form method="post" action="<?= e($action) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="activate">
                                <button class="btn btn--ghost btn--xs" type="submit">Mở lại</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e($action) ?>"
                                  data-confirm="Tạm ngưng tài khoản <?= e((string) $row['username']) ?>?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="suspend">
                                <button class="btn btn--ghost btn--xs" type="submit" <?= $isSelf ? 'disabled' : '' ?>>
                                    Tạm ngưng
                                </button>
                            </form>
                        <?php endif; ?>

                        <form method="post" action="<?= e($action) ?>"
                              data-confirm="Đặt lại mật khẩu cho <?= e((string) $row['username']) ?>? Hệ thống sẽ sinh mật khẩu tạm.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reset_password">
                            <button class="btn btn--ghost btn--xs" type="submit">Đặt lại mật khẩu</button>
                        </form>

                        <form class="quota-form" method="post" action="<?= e($action) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="quota">
                            <label>
                                <span class="sr-only">Hạn mức liên kết</span>
                                <input class="input input--xs" type="number" name="link_quota" min="0" step="1"
                                       value="<?= (int) $row['link_quota'] ?>" title="0 = không giới hạn">
                            </label>
                            <button class="btn btn--ghost btn--xs" type="submit">Đặt hạn mức</button>
                        </form>

                        <form method="post" action="<?= e($action) ?>"
                              data-confirm="Xoá tài khoản <?= e((string) $row['username']) ?>? Các liên kết cũ vẫn hoạt động nhưng không còn chủ.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <button class="btn btn--danger btn--xs" type="submit" <?= $isSelf ? 'disabled' : '' ?>>Xoá</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if ($users === []): ?>
                <div class="card empty-state">
                    <span class="empty-state__emoji" aria-hidden="true">🔍</span>
                    <h2>Không tìm thấy người dùng nào</h2>
                    <a class="btn btn--ghost" href="<?= e(url('/quan-tri/nguoi-dung')) ?>">Bỏ lọc</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
