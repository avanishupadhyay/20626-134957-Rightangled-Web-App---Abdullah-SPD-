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
                <a class="nav-link {{ request()->routeIs('prescriber_orders.index', 'prescriber_orders.view') ? 'active' : '' }}"
                    href="{{ route('prescriber_orders.index') }}">
                    <div class="sb-nav-link-icon"><i class="fa fa-stethoscope"></i></div>
                    Prescribers
                </a>
                <a class="nav-link {{ request()->routeIs('checker_orders.index', 'checker_orders.view') ? 'active' : '' }}"
                    href="{{ route('checker_orders.index') }}">
                    <div class="sb-nav-link-icon"><i class="fa fa-check-square"></i>
</div>
                    Checkers
                </a>
                <a class="nav-link {{ request()->routeIs('dispenser_orders.index', 'dispenser_orders.view') ? 'active' : '' }}"
                    href="{{ route('dispenser_orders.index') }}">
                    <div class="sb-nav-link-icon"><i class="fas fa-shipping-fast"></i>
</div>
                    Dispenser
                </a>

                <a class="nav-link {{ request()->routeIs('admin.report') ? 'active' : '' }}"
                    href="{{ url('/admin/report') }}"><i class="fas fa-chart-bar"></i>&nbsp;Report
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

                    <a class="nav-link {{ request()->routeIs('admin.email-templates.index') ? 'active' : '' }}"
                    href="{{ url('/admin/email-templates') }}"><i class="fa fa-envelope"></i>&nbsp;email-templates
                    </a>
                    

                @endif

                {{-- <div class="sb-sidenav-menu-heading">Manage </div> --}}
                <a class="nav-link {{ request()->routeIs('admin.stores.index') ? 'active' : '' }}"
                    href="{{ route('admin.stores.index') }}">
                    <div class="sb-nav-link-icon"><i class="fa-solid fa-gear"></i></div>
                    Store
                </a>

                @role('Admin')
                    <div class="sb-sidenav-menu-heading">Manage Users</div>
                    <a class="nav-link {{ request()->routeIs('users.index') ? 'active' : '' }}"
                        href="{{ route('users.index') }}">
                        <div class="sb-nav-link-icon"><i class="fas fa-user"></i></div>
                        Users
                    </a>
                @endrole
                 @role('Admin')
                <div class="sb-sidenav-menu-heading">Modules</div>
                <a class="nav-link {{ request()->routeIs('orders.index') ? 'active' : '' }}"
                    href="{{ route('orders.index') }}">
                    <div class="sb-nav-link-icon"><i class="fa fa-first-order" aria-hidden="true"></i>
                    </div>
                    Orders
                </a>
                @endrole
            </div>
    </nav>
</div>
