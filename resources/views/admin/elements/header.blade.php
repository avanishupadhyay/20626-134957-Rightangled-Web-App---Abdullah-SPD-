<div id="layoutSidenav_nav">
    <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
        <div class="main-profile">
            <!--<div class="image-bx">
                <img src="https://demo.w3cms.in/lemars/public/images/no-user.png" alt="User profile">
                <a href="#"><i class="fa fa-cog" aria-hidden="true"></i></a>
            </div>-->
            <h5 class="name"><span class="font-w400">Hello,</span> {{ auth()->user()->name }} </h5>
            <p class="role"></p>
            <p class="email">{{ auth()->user()->email }}</p>
        </div>
        <div class="sb-sidenav-menu">
            <div class="nav">
                <div class="sb-sidenav-menu-heading">Core</div>
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                    href="{{ url('/admin/dashboard') }}">
                    <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                    Dashboard
                </a>

                <div class="sb-sidenav-menu-heading">Configurations</div>



                @php
                    $configuration_menu = getConfigurationMenu();
                    $collapsed = 'collapsed';
                    $prefix = '';
                    if (request()->routeIs('admin.configurations.admin_prefix')) {
                        $collapsed = '';
                        $prefix = \Illuminate\Support\Facades\Request::segment(4);
                    }
                @endphp

                @if (!empty($configuration_menu))

                    <a class="nav-link {{ $collapsed }}" href="#" data-bs-toggle="collapse"
                        data-bs-target="#Configurations" aria-expanded="false" aria-controls="collapseLayouts">
                        <div class="sb-nav-link-icon"><i class="fa-solid fa-gear"></i></div>
                        Global Configurations
                        <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.configurations.admin_prefix') ? 'show' : '' }}"
                        id="Configurations" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                        <nav class="sb-sidenav-menu-nested nav">
                            @forelse($configuration_menu as $config_menu)
                                <a class="nav-link {{ $prefix == $config_menu ? 'active' : '' }}"
                                    href="{{ route('admin.configurations.admin_prefix', $config_menu) }}">{{ $config_menu }}</a>
                            @empty
                            @endforelse
                        </nav>
                    </div>

                @endif

                @role('Admin')
                    <div class="sb-sidenav-menu-heading">Manage Users</div>
                    <a class="nav-link {{ request()->routeIs('users.index') ? 'active' : '' }}"
                        href="{{ route('users.index') }}">
                        <div class="sb-nav-link-icon"><i class="fas fa-user"></i></div>
                        Users
                    </a>
                </div>
            @endrole
        </div>
    </nav>
</div>
