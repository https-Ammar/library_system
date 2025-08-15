<aside :class="sidebarToggle ? 'translate-x-0 xl:w-[90px]' : '-translate-x-full'"
    class="sidebar fixed top-0 left-0 z-9999 flex h-screen w-[290px] flex-col overflow-y-auto border-l border-gray-200 bg-white px-5 transition-all duration-300 xl:static xl:translate-x-0 dark:border-gray-800 dark:bg-black"
    @click.outside="sidebarToggle = false"
    x-data="{ selected: $persist('Dashboard'), page: window.location.pathname.split('/').pop().replace('.php', '') }">

    <div class="no-scrollbar flex flex-col overflow-y-auto duration-300 ease-linear mt-8">
        <nav>
            <div>
                <h3 class="mb-4 text-xs leading-[20px] text-gray-400 uppercase">
                    <span class="menu-group-title" :class="sidebarToggle ? 'xl:hidden' : ''">القائمة</span>
                    <i :class="sidebarToggle ? 'xl:block hidden bi bi-three-dots' : 'hidden'"></i>
                </h3>

                <ul class="mb-6 flex flex-col gap-1">
                    <li>
                        <a href="#" @click.prevent="selected = (selected === 'Dashboard' ? '' : 'Dashboard')"
                            class="menu-item group"
                            :class="(selected === 'Dashboard') || (['ecommerce','analytics','marketing','crm','stocks','saas','logistics'].includes(page)) ? 'menu-item-active' : 'menu-item-inactive'">
                            <i
                                :class="(selected === 'Dashboard') || (['ecommerce','analytics','marketing','crm','stocks'].includes(page)) ? 'menu-item-icon-active bi bi-speedometer2' : 'menu-item-icon-inactive bi bi-speedometer2'"></i>
                            <span class="menu-item-text" :class="sidebarToggle ? 'xl:hidden' : ''">لوحة التحكم</span>
                        </a>

                        <div class="translate transform overflow-hidden"
                            :class="(selected === 'Dashboard') ? 'block' : 'hidden'">
                            <ul :class="sidebarToggle ? 'xl:hidden' : 'flex'"
                                class="menu-dropdown mt-2 flex flex-col gap-1 pl-9">
                                <li>
                                    <a href="http://localhost:8888/library_system/" class="menu-dropdown-item group"
                                        :class="page === '' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        <i class="bi bi-house-door"></i> الرئيسية
                                    </a>
                                </li>



                                <li>
                                    <a href="http://localhost:8888/library_system/pages/grade.php"
                                        class="menu-dropdown-item group"
                                        :class="page === 'grade' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        <i class="bi bi-diagram-3"></i> المراحل الدراسية
                                    </a>
                                </li>
                                <li>
                                    <a href="http://localhost:8888/library_system/pages/teachers.php"
                                        class="menu-dropdown-item group"
                                        :class="page === 'teachers' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        <i class="bi bi-person-badge"></i> المدرسين
                                    </a>
                                </li>
                                <li>
                                    <a href="http://localhost:8888/library_system/pages/student.php"
                                        class="menu-dropdown-item group"
                                        :class="page === 'student' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        <i class="bi bi-people"></i> الطلاب
                                    </a>
                                </li>
                                <li>
                                    <a href="http://localhost:8888/library_system/pages/create_book.php"
                                        class="menu-dropdown-item group"
                                        :class="page === 'create_book' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        <i class="bi bi-journal-plus"></i> إنشاء كتاب
                                    </a>
                                </li>
                                <li>
                                    <a href="http://localhost:8888/library_system/pages/stock.php"
                                        class="menu-dropdown-item group"
                                        :class="page === 'stock' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        <i class="bi bi-box-seam"></i> المخزون
                                    </a>
                                </li>
                                <li>
                                    <a href="http://localhost:8888/library_system/pages/expenses.php"
                                        class="menu-dropdown-item group"
                                        :class="page === 'expenses' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        <i class="bi bi-cash-stack"></i> المصروفات
                                    </a>
                                </li>
                                <li>
                                    <a href="http://localhost:8888/library_system/pages/book_reservations.php"
                                        class="menu-dropdown-item group"
                                        :class="page === 'book_reservations' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        <i class="bi bi-bookmark-check"></i> الحجوزات
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                </ul>
            </div>

            <div :class="sidebarToggle ? 'xl:hidden' : ''"
                class="mx-auto mb-10 w-full max-w-60 rounded-2xl bg-gray-50 px-4 py-5 text-center dark:bg-white/[0.03]">
                <p class="text-theme-sm mb-4 text-gray-500 dark:text-gray-400 text-center">
                    <span class="font-medium text-primary-600 dark:text-primary-400 text-center">Eng - Ammar
                        Ahmed</span> &copy;
                    <span x-text="new Date().getFullYear()"></span>
                </p>
                <a href="http://localhost:8888/library_system/auth/logout.php" target="_blank" rel="nofollow"
                    class="bg-brand-500 text-theme-sm hover:bg-brand-600 flex items-center justify-center gap-2 rounded-lg p-3 font-medium text-white">
                    <i class="bi bi-box-arrow-right"></i> تسجيل الخروج
                </a>
            </div>
        </nav>
    </div>
</aside>