<?php include_once MODX_MANAGER_PATH . 'includes/header.inc.php'; ?>

<div id="content-area">
    <h1>
        @hasSection('icon')
            <i class="fa fa-@yield('icon')"></i>
        @endif
        @yield('title')
    </h1>

    <div id="actions">
        <div class="btn-group">
            @yield('actions')
        </div>
    </div>

    <div class="sectionBody">
        @yield('content')
    </div>
</div>

@stack('modals')
@stack('scripts')

<?php include_once MODX_MANAGER_PATH . 'includes/footer.inc.php'; ?>