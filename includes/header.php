<aside :class="sidebarToggle ? 'translate-x-0 xl:w-[90px]' : '-translate-x-full'"
    class="sidebar fixed top-0 left-0 z-9999 flex h-screen w-[290px] flex-col overflow-y-auto border-l border-gray-200 bg-white px-5 transition-all duration-300 xl:static xl:translate-x-0 dark:border-gray-800 dark:bg-black"
    @click.outside="sidebarToggle = false" x-data="{
        selected: $persist('Dashboard'),
        page: window.location.pathname.split('/').pop().replace('.php', '')
    }">
    <div class="no-scrollbar flex flex-col overflow-y-auto duration-300 ease-linear mt-8">
        <nav>
            <div>
                <h3 class="mb-4 text-xs leading-[20px] text-gray-400 uppercase">
                    <span class="menu-group-title" :class="sidebarToggle ? 'xl:hidden' : ''">
                        MENU
                    </span>
                    <i :class="sidebarToggle ? 'xl:block hidden bi bi-three-dots' : 'hidden'"></i>
                </h3>

                <ul class="mb-6 flex flex-col gap-1">
                    <li>
                        <a href="#" @click.prevent="selected = (selected === 'Dashboard' ? '' : 'Dashboard')"
                            class="menu-item group"
                            :class="(selected === 'Dashboard') || (['ecommerce','analytics','marketing','crm','stocks','saas','logistics'].includes(page)) ? 'menu-item-active' : 'menu-item-inactive'">
                            <i
                                :class="(selected === 'Dashboard') || (['ecommerce','analytics','marketing','crm','stocks'].includes(page)) ? 'menu-item-icon-active bi bi-grid' : 'menu-item-icon-inactive bi bi-grid'"></i>
                            <span class="menu-item-text" :class="sidebarToggle ? 'xl:hidden' : ''">
                                Dashboard
                            </span>
                        </a>

                        <div class="translate transform overflow-hidden"
                            :class="(selected === 'Dashboard') ? 'block' : 'hidden'">
                            <ul :class="sidebarToggle ? 'xl:hidden' : 'flex'"
                                class="menu-dropdown mt-2 flex flex-col gap-1 pl-9">
                                <li>
                                    <a href="./pages/stock.php" class="menu-dropdown-item group"
                                        :class="page === 'stock' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        المخزون
                                    </a>
                                </li>
                                <li>
                                    <a href="./pages/student.php" class="menu-dropdown-item group"
                                        :class="page === 'student' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        الطلاب
                                    </a>
                                </li>
                                <li>
                                    <a href="./pages/teachers.php" class="menu-dropdown-item group"
                                        :class="page === 'teachers' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        المدرسين
                                    </a>
                                </li>
                                <li>
                                    <a href="./pages/grade.php" class="menu-dropdown-item group"
                                        :class="page === 'grade' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        المراحل الدراسية
                                    </a>
                                </li>
                                <li>
                                    <a href="./pages/create_book.php" class="menu-dropdown-item group"
                                        :class="page === 'create_book' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        انشاء كتاب
                                    </a>
                                </li>
                                <li>
                                    <a href="./pages/expenses.php" class="menu-dropdown-item group"
                                        :class="page === 'expenses' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        المصروفات
                                    </a>
                                </li>
                                <li>
                                    <a href="./pages/book_reservations.php" class="menu-dropdown-item group"
                                        :class="page === 'book_reservations' ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                        الحجوزات
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