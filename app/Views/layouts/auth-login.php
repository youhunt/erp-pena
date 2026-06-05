<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(lang('Auth.login')) ?> | PENA ERP</title>
    <link rel="shortcut icon" href="<?= base_url('assets/skote/images/Logo.png') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/skote/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/skote/css/icons.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/skote/css/app.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/pena/app.css') ?>">
</head>

<body>
    <div class="account-pages my-5 pt-sm-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6 col-xl-5">
                    <div class="card overflow-hidden">
                        <div class="bg-primary bg-soft">
                            <div class="row">
                                <div class="col-7">
                                    <div class="text-primary p-4">
                                        <h5 class="text-primary">Welcome Back</h5>
                                        <p>Sign in to continue to PENA ERP.</p>
                                    </div>
                                </div>
                                <div class="col-5 align-self-end">
                                    <img src="<?= base_url('assets/skote/images/profile-img.png') ?>" alt="PENA ERP login" class="img-fluid">
                                </div>
                            </div>
                        </div>

                        <div class="card-body pt-0">
                            <div class="auth-logo">
                                <a href="<?= site_url('/') ?>" class="auth-logo-light">
                                    <div class="avatar-md profile-user-wid mb-4">
                                        <span class="avatar-title rounded-circle bg-light">
                                            <img src="<?= base_url('assets/skote/images/logo-sm-light.png') ?>" alt="PENA ERP" class="rounded-circle" height="34">
                                        </span>
                                    </div>
                                </a>

                                <a href="<?= site_url('/') ?>" class="auth-logo-dark">
                                    <div class="avatar-md profile-user-wid mb-4">
                                        <span class="avatar-title rounded-circle bg-light">
                                            <img src="<?= base_url('assets/skote/images/logo-sm-dark.png') ?>" alt="PENA ERP" class="rounded-circle" height="34">
                                        </span>
                                    </div>
                                </a>
                            </div>

                            <div class="p-2">
                                <?php if (session('error') !== null): ?>
                                    <div class="alert alert-danger" role="alert"><?= esc(session('error')) ?></div>
                                <?php elseif (session('errors') !== null): ?>
                                    <div class="alert alert-danger" role="alert">
                                        <?php if (is_array(session('errors'))): ?>
                                            <?php foreach (session('errors') as $error): ?>
                                                <?= esc($error) ?><br>
                                            <?php endforeach ?>
                                        <?php else: ?>
                                            <?= esc(session('errors')) ?>
                                        <?php endif ?>
                                    </div>
                                <?php endif ?>

                                <?php if (session('message') !== null): ?>
                                    <div class="alert alert-success" role="alert"><?= esc(session('message')) ?></div>
                                <?php endif ?>

                                <form class="form-horizontal" method="post" action="<?= url_to('login') ?>">
                                    <?= csrf_field() ?>

                                    <div class="mb-3">
                                        <label for="email" class="form-label"><?= esc(lang('Auth.email')) ?></label>
                                        <input
                                            type="email"
                                            class="form-control"
                                            id="email"
                                            name="email"
                                            inputmode="email"
                                            autocomplete="email"
                                            placeholder="Enter email address"
                                            value="<?= old('email') ?>"
                                            required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="password" class="form-label"><?= esc(lang('Auth.password')) ?></label>
                                        <div class="input-group auth-pass-inputgroup">
                                            <input
                                                type="password"
                                                class="form-control"
                                                id="password"
                                                name="password"
                                                inputmode="text"
                                                autocomplete="current-password"
                                                placeholder="Enter password"
                                                aria-label="<?= esc(lang('Auth.password')) ?>"
                                                required>
                                            <button class="btn btn-light" type="button" id="password-addon" aria-label="Show password">
                                                <i class="mdi mdi-eye-outline"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <?php if (setting('Auth.sessionConfig')['allowRemembering']): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="remember-check" name="remember" <?php if (old('remember')): ?>checked<?php endif ?>>
                                            <label class="form-check-label" for="remember-check">
                                                <?= esc(lang('Auth.rememberMe')) ?>
                                            </label>
                                        </div>
                                    <?php endif ?>

                                    <div class="mt-3 d-grid">
                                        <button class="btn btn-primary waves-effect waves-light" type="submit">
                                            <?= esc(lang('Auth.login')) ?>
                                        </button>
                                    </div>

                                    <?php if (setting('Auth.allowMagicLinkLogins')): ?>
                                        <div class="mt-4 text-center">
                                            <a href="<?= url_to('magic-link') ?>" class="text-muted">
                                                <i class="mdi mdi-lock me-1"></i> <?= esc(lang('Auth.forgotPassword')) ?>
                                            </a>
                                        </div>
                                    <?php endif ?>

                                    <?php if (setting('Auth.allowRegistration')): ?>
                                        <div class="mt-3 text-center">
                                            <p class="mb-0"><?= esc(lang('Auth.needAccount')) ?> <a href="<?= url_to('register') ?>" class="fw-medium text-primary"><?= esc(lang('Auth.register')) ?></a></p>
                                        </div>
                                    <?php endif ?>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 text-center">
                        <p class="mb-0">&copy; <?= date('Y') ?> PENA ERP. Enterprise ERP Foundation</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= base_url('assets/skote/libs/jquery/jquery.min.js') ?>"></script>
    <script src="<?= base_url('assets/skote/libs/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= base_url('assets/skote/libs/metismenu/metisMenu.min.js') ?>"></script>
    <script src="<?= base_url('assets/skote/libs/simplebar/simplebar.min.js') ?>"></script>
    <script src="<?= base_url('assets/skote/libs/node-waves/waves.min.js') ?>"></script>
    <script src="<?= base_url('assets/skote/js/app.js') ?>"></script>
    <script>
        document.getElementById('password-addon')?.addEventListener('click', function() {
            const input = document.getElementById('password');
            if (!input) {
                return;
            }

            input.type = input.type === 'password' ? 'text' : 'password';
        });
    </script>
</body>

</html>