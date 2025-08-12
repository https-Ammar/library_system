<aside :class="sidebarToggle ? 'translate-x-0 xl:w-[90px]' : '-translate-x-full'"
    class="sidebar fixed top-0 left-0 z-9999 flex h-screen w-[290px] flex-col overflow-y-auto border-r border-gray-200 bg-white px-5 transition-all duration-300 xl:static xl:translate-x-0 dark:border-gray-800 dark:bg-black"
    @click.outside="sidebarToggle = false">
    <div class="no-scrollbar flex flex-col overflow-y-auto duration-300 ease-linear mt-8">
        <nav x-data="{selected: $persist('Dashboard')}">
            <div>
                <h3 class="mb-4 text-xs leading-[20px] text-gray-400 uppercase">
                    <span class="menu-group-title" :class="sidebarToggle ? 'xl:hidden' : ''">
                        MENU
                    </span>
                    <i :class="sidebarToggle ? 'xl:block hidden bi bi-three-dots' : 'hidden'"></i>
                </h3>

                <ul class="mb-6 flex flex-col gap-1">
                    <li>
                        <a href="#" @click.prevent="selected = (selected === 'Dashboard' ? '':'Dashboard')"
                            class="menu-item group"
                            :class=" (selected === 'Dashboard') || (page === 'ecommerce' || page === 'analytics' || page === 'marketing' || page === 'crm' || page === 'stocks' || page === 'saas' || page === 'logistics') ? 'menu-item-active' : 'menu-item-inactive'">
                            <i
                                :class="(selected === 'Dashboard') || (page === 'ecommerce' || page === 'analytics' || page === 'marketing' || page === 'crm' || page === 'stocks') ? 'menu-item-icon-active bi bi-grid' :'menu-item-icon-inactive bi bi-grid'"></i>

                            <span class="menu-item-text" :class="sidebarToggle ? 'xl:hidden' : ''">
                                Dashboard
                            </span>

                            <i class="menu-item-arrow bi bi-chevron-down"
                                :class="[(selected === 'Dashboard') ? 'menu-item-arrow-active' : 'menu-item-arrow-inactive', sidebarToggle ? 'xl:hidden' : '' ]"></i>
                        </a>

                        <div class="translate transform overflow-hidden"
                            :class="(selected === 'Dashboard') ? 'block' :'hidden'">
                            <ul :class="sidebarToggle ? 'xl:hidden' : 'flex'"
                                class="menu-dropdown mt-2 flex flex-col gap-1 pl-9">
                                <li>
                                    <a href="./pages/stock.php" class="menu-dropdown-item group"
                                        :class="page === 'analytics' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        المخزون
                                    </a>
                                </li>
                                <li>
                                    <a class="menu-dropdown-item group" href="./pages/student.php"
                                        :class="page === 'marketing' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        الطلاب
                                    </a>
                                </li>
                                <li>
                                    <a href="./pages/teachers.php" class="menu-dropdown-item group"
                                        :class="page === 'crm' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        المدرسين
                                    </a>
                                </li>
                                <li>
                                    <a href="./pages/grade.php" class="menu-dropdown-item group"
                                        :class="page === 'stocks' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        المراحل الدراسية
                                    </a>
                                </li>
                                <li>
                                    <a href="#" class="menu-dropdown-item group"
                                        :class="page === 'stocks' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        Advertisements
                                    </a>
                                </li>

                                <li>
                                    <a href="#" class="menu-dropdown-item group"
                                        :class="page === 'stocks' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        Categories
                                    </a>
                                </li>

                                <li>
                                    <a href="./orders/order.php" class="menu-dropdown-item group"
                                        :class="page === 'stocks' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        Order Management
                                    </a>
                                </li>


                            </ul>
                        </div>
                    </li>
                </ul>
            </div>

            <div>
                <h3 class="mb-4 text-xs leading-[20px] text-gray-400 uppercase">
                    <span class="menu-group-title" :class="sidebarToggle ? 'xl:hidden' : ''">
                        Support
                    </span>
                    <i :class="sidebarToggle ? 'xl:block hidden bi bi-three-dots' : 'hidden'"></i>
                </h3>

                <ul class="mb-6 flex flex-col gap-1">
                    <li>
                        <a href="chat.html" @click="selected = (selected === 'Chat' ? '':'Chat')"
                            class="menu-item group"
                            :class=" (selected === 'Chat') && (page === 'chat') ? 'menu-item-active' : 'menu-item-inactive'">
                            <i
                                :class="(selected === 'Chat') && (page === 'chat') ? 'menu-item-icon-active bi bi-chat' :'menu-item-icon-inactive bi bi-chat'"></i>

                            <span class="menu-item-text" :class="sidebarToggle ? 'xl:hidden' : ''">
                                Chat
                            </span>
                        </a>
                    </li>

                    <li>
                        <a href="#" @click.prevent="selected = (selected === 'Support' ? '':'Support')"
                            class="menu-item group"
                            :class="(selected === 'Support') || (page === 'ticketLists' || page === 'ticketReply') ? 'menu-item-active' : 'menu-item-inactive'">
                            <i
                                :class="(selected === 'Support') || (page === 'ticketLists' || page === 'ticketLists') ? 'menu-item-icon-active bi bi-ticket' :'menu-item-icon-inactive bi bi-ticket'"></i>

                            <span class="menu-item-text" :class="sidebarToggle ? 'xl:hidden' : ''">
                                Support Ticket
                            </span>
                            <span :class="sidebarToggle ? 'xl:hidden' : ''"
                                class="absolute right-10 flex items-center gap-1">
                                <span class="menu-dropdown-badge"
                                    :class="page === 'products' ? 'menu-dropdown-badge-active' : 'menu-dropdown-badge-inactive'">
                                    New
                                </span>
                            </span>

                            <i class="menu-item-arrow bi bi-chevron-down"
                                :class="[(selected === 'Support') ? 'menu-item-arrow-active' : 'menu-item-arrow-inactive', sidebarToggle ? 'xl:hidden' : '' ]"></i>
                        </a>

                        <div class="translate transform overflow-hidden"
                            :class="(selected === 'Support') ? 'block' :'hidden'">
                            <ul :class="sidebarToggle ? 'xl:hidden' : 'flex'"
                                class="menu-dropdown mt-2 flex flex-col gap-1 pl-9">
                                <li>
                                    <a href="support-tickets.html" class="menu-dropdown-item group"
                                        :class="page === 'ticketLists' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        Ticket List
                                    </a>
                                </li>
                                <li>
                                    <a href="support-ticket-reply.html" class="menu-dropdown-item group"
                                        :class="page === 'ticketReply' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        Ticket Reply
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    <li>
                        <a href="#" @click.prevent="selected = (selected === 'Email' ? '':'Email')"
                            class="menu-item group"
                            :class="(selected === 'Email') || (page === 'inbox' || page === 'inboxDetails') ? 'menu-item-active' : 'menu-item-inactive'">
                            <i
                                :class="(selected === 'Email') || (page === 'inbox' || page === 'inboxDetails') ? 'menu-item-icon-active bi bi-envelope' :'menu-item-icon-inactive bi bi-envelope'"></i>

                            <span class="menu-item-text" :class="sidebarToggle ? 'xl:hidden' : ''">
                                Email
                            </span>

                            <i class="menu-item-arrow bi bi-chevron-down"
                                :class="[(selected === 'Email') ? 'menu-item-arrow-active' : 'menu-item-arrow-inactive', sidebarToggle ? 'xl:hidden' : '' ]"></i>
                        </a>

                        <div class="translate transform overflow-hidden"
                            :class="(selected === 'Email') ? 'block' :'hidden'">
                            <ul :class="sidebarToggle ? 'xl:hidden' : 'flex'"
                                class="menu-dropdown mt-2 flex flex-col gap-1 pl-9">
                                <li>
                                    <a href="inbox.html" class="menu-dropdown-item group"
                                        :class="page === 'inbox' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        Inbox
                                    </a>
                                </li>
                                <li>
                                    <a href="inbox-details.html" class="menu-dropdown-item group"
                                        :class="page === 'inboxDetails' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        Details
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                </ul>
            </div>

            <div>
                <h3 class="mb-4 text-xs leading-[20px] text-gray-400 uppercase">
                    <span class="menu-group-title" :class="sidebarToggle ? 'xl:hidden' : ''">
                        Others
                    </span>
                    <i :class="sidebarToggle ? 'xl:block hidden bi bi-three-dots' : 'hidden'"></i>
                </h3>

                <ul class="mb-6 flex flex-col gap-1">
                    <li>
                        <a href="#" @click.prevent="selected = (selected === 'Charts' ? '':'Charts')"
                            class="menu-item group"
                            :class="(selected === 'Charts') || (page === 'lineChart' || page === 'barChart' || page === 'pieChart') ? 'menu-item-active' : 'menu-item-inactive'">
                            <i
                                :class="(selected === 'Charts') || (page === 'lineChart' || page === 'barChart' || page === 'pieChart') ? 'menu-item-icon-active bi bi-bar-chart' :'menu-item-icon-inactive bi bi-bar-chart'"></i>

                            <span class="menu-item-text" :class="sidebarToggle ? 'xl:hidden' : ''">
                                Charts
                            </span>

                            <i class="menu-item-arrow bi bi-chevron-down"
                                :class="[(selected === 'Charts') ? 'menu-item-arrow-active' : 'menu-item-arrow-inactive', sidebarToggle ? 'xl:hidden' : '' ]"></i>
                        </a>

                        <div class="translate transform overflow-hidden"
                            :class="(selected === 'Charts') ? 'block' :'hidden'">
                            <ul :class="sidebarToggle ? 'lg:hidden' : 'flex'"
                                class="menu-dropdown mt-2 flex flex-col gap-1 pl-9">
                                <li>
                                    <a href="line-chart.html" class="menu-dropdown-item group"
                                        :class="page === 'lineChart' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        Line Chart
                                    </a>
                                </li>
                                <li>
                                    <a href="bar-chart.html" class="menu-dropdown-item group"
                                        :class="page === 'barChart' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        Bar Chart
                                    </a>
                                </li>
                                <li>
                                    <a href="pie-chart.html" class="menu-dropdown-item group"
                                        :class="page === 'pieChart' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        Pie Chart
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    <li>
                        <a href="#" @click.prevent="selected = (selected === 'Authentication' ? '':'Authentication')"
                            class="menu-item group"
                            :class="(selected === 'Authentication') || (page === 'basicChart' || page === 'advancedChart') ? 'menu-item-active' : 'menu-item-inactive'">
                            <i
                                :class="(selected === 'Authentication') || (page === 'basicChart' || page === 'advancedChart') ? 'menu-item-icon-active bi bi-shield-lock' :'menu-item-icon-inactive bi bi-shield-lock'"></i>

                            <span class="menu-item-text" :class="sidebarToggle ? 'xl:hidden' : ''">
                                Authentication
                            </span>

                            <i class="menu-item-arrow bi bi-chevron-down"
                                :class="[(selected === 'Authentication') ? 'menu-item-arrow-active' : 'menu-item-arrow-inactive', sidebarToggle ? 'xl:hidden' : '' ]"></i>
                        </a>

                        <div class="translate transform overflow-hidden"
                            :class="(selected === 'Authentication') ? 'block' :'hidden'">
                            <ul :class="sidebarToggle ? 'lg:hidden' : 'flex'"
                                class="menu-dropdown mt-2 flex flex-col gap-1 pl-9">
                                <li>
                                    <a href="signin.html" class="menu-dropdown-item group"
                                        :class="page === 'signin' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        Sign In
                                    </a>
                                </li>
                                <li>
                                    <a href="signup.html" class="menu-dropdown-item group"
                                        :class="page === 'signup' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        Sign Up
                                    </a>
                                </li>
                                <li>
                                    <a href="reset-password.html" class="menu-dropdown-item group"
                                        :class="page === 'resetPassword' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        Reset Password
                                    </a>
                                </li>
                                <li>
                                    <a href="two-step-verification.html" class="menu-dropdown-item group"
                                        :class="page === 'twoStepVerification' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        Two Step Verification
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                </ul>
            </div>
        </nav>
    </div>
</aside>