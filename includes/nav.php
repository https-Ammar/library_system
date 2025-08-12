<header x-data="{menuToggle: false}"
    class="sticky top-0 z-99999 flex w-full border-gray-200 bg-white xl:border-b dark:border-gray-800 dark:bg-gray-900">
    <div class="flex grow flex-col items-center justify-between xl:flex-row xl:px-6">
        <div
            class="flex w-full items-center justify-between gap-2 border-b border-gray-200 px-3 py-3 sm:gap-4 lg:py-4 xl:justify-normal xl:border-b-0 xl:px-0 dark:border-gray-800">
            <button
                :class="sidebarToggle ? 'xl:bg-transparent dark:xl:bg-transparent bg-gray-100 dark:bg-gray-800' : ''"
                class="z-99999 flex h-10 w-10 items-center justify-center rounded-lg border-gray-200 text-gray-500 xl:h-11 xl:w-11 xl:border dark:border-gray-800 dark:text-gray-400"
                @click.stop="sidebarToggle = !sidebarToggle">
                <i class="bi bi-list xl:block hidden" style="font-size:16px;"></i>
                <i :class="sidebarToggle ? 'hidden' : 'block xl:hidden'" class="bi bi-list" style="font-size:24px;"></i>
                <i :class="sidebarToggle ? 'block xl:hidden' : 'hidden'" class="bi bi-x" style="font-size:24px;"></i>
            </button>

            <a href="index.html" class="xl:hidden">
                <img class="dark:hidden" src="src/images/logo/logo.svg" alt="Logo" />
                <img class="hidden dark:block" src="./assets/" alt="Logo" />
            </a>

            <button
                class="z-99999 flex h-10 w-10 items-center justify-center rounded-lg text-gray-700 hover:bg-gray-100 xl:hidden dark:text-gray-400 dark:hover:bg-gray-800"
                :class="menuToggle ? 'bg-gray-100 dark:bg-gray-800' : ''" @click.stop="menuToggle = !menuToggle">
                <i class="bi bi-three-dots-vertical" style="font-size:24px;"></i>
            </button>

            <div class="hidden xl:block">
                <form>
                    <div class="relative">
                        <span class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2">
                            <i class="bi bi-search text-gray-500 dark:text-gray-400" style="font-size:20px;"></i>
                        </span>
                        <input id="search-input" type="text" placeholder="Search or type command..."
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-200 bg-transparent py-2.5 pr-14 pl-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden xl:w-[430px] dark:border-gray-800 dark:bg-gray-900 dark:bg-white/[0.03] dark:text-white/90 dark:placeholder:text-white/30" />
                        <button id="search-button"
                            class="absolute top-1/2 right-2.5 inline-flex -translate-y-1/2 items-center gap-0.5 rounded-lg border border-gray-200 bg-gray-50 px-[7px] py-[4.5px] text-xs -tracking-[0.2px] text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400">
                            <span> ⌘ </span>
                            <span> K </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div :class="menuToggle ? 'flex' : 'hidden'"
            class="shadow-theme-md w-full items-center justify-between gap-4 px-5 py-4 xl:flex xl:justify-end xl:px-0 xl:shadow-none">
            <div class="2xsm:gap-3 flex items-center gap-2">
                <button
                    class="hover:text-dark-900 relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                    @click.prevent="darkMode = !darkMode">
                    <i class="bi bi-moon-fill dark:block hidden" style="font-size:20px;"></i>
                    <i class="bi bi-sun-fill dark:hidden" style="font-size:20px;"></i>
                </button>

                <div class="relative" x-data="{ dropdownOpen: false, notifying: true }"
                    @click.outside="dropdownOpen = false">
                    <button
                        class="hover:text-dark-900 relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                        @click.prevent="dropdownOpen = ! dropdownOpen; notifying = false">
                        <span :class="!notifying ? 'hidden' : 'flex'"
                            class="absolute top-0.5 right-0 z-1 h-2 w-2 rounded-full bg-orange-400">
                            <span
                                class="absolute -z-1 inline-flex h-full w-full animate-ping rounded-full bg-orange-400 opacity-75"></span>
                        </span>
                        <i class="bi bi-bell-fill" style="font-size:20px;"></i>
                    </button>

                    <div x-show="dropdownOpen"
                        class="shadow-theme-lg dark:bg-gray-dark absolute -right-[240px] mt-[17px] flex h-[480px] w-[350px] flex-col rounded-2xl border border-gray-200 bg-white p-3 sm:w-[361px] lg:right-0 dark:border-gray-800">
                        <div
                            class="mb-3 flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
                            <h5 class="text-lg font-semibold text-gray-800 dark:text-white/90">Notification</h5>
                            <button @click="dropdownOpen = false" class="text-gray-500 dark:text-gray-400">
                                <i class="bi bi-x" style="font-size:24px;"></i>
                            </button>
                        </div>

                        <ul class="custom-scrollbar flex h-auto flex-col overflow-y-auto">
                            <li>
                                <a class="flex gap-3 rounded-lg border-b border-gray-100 p-3 px-4.5 py-3 hover:bg-gray-100 dark:border-gray-800 dark:hover:bg-white/5"
                                    href="#">
                                    <span class="relative z-1 block h-10 w-full max-w-10 rounded-full">
                                        <img src="src/images/user/user-02.jpg" alt="User"
                                            class="overflow-hidden rounded-full" />
                                        <span
                                            class="bg-success-500 absolute right-0 bottom-0 z-10 h-2.5 w-full max-w-2.5 rounded-full border-[1.5px] border-white dark:border-gray-900"></span>
                                    </span>

                                    <span class="block">
                                        <span class="text-theme-sm mb-1.5 block text-gray-500 dark:text-gray-400">
                                            <span class="font-medium text-gray-800 dark:text-white/90">Terry
                                                Franci</span> requests permission to change <span
                                                class="font-medium text-gray-800 dark:text-white/90">Project - Nganter
                                                App</span>
                                        </span>
                                        <span
                                            class="text-theme-xs flex items-center gap-2 text-gray-500 dark:text-gray-400">
                                            <span>Project</span>
                                            <span class="h-1 w-1 rounded-full bg-gray-400"></span>
                                            <span>5 min ago</span>
                                        </span>
                                    </span>
                                </a>
                            </li>
                        </ul>

                        <a href="#"
                            class="text-theme-sm shadow-theme-xs mt-3 flex justify-center rounded-lg border border-gray-300 bg-white p-3 font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">View
                            All Notification</a>
                    </div>
                </div>
            </div>

            <div class="relative" x-data="{ dropdownOpen: false }" @click.outside="dropdownOpen = false">
                <a class="flex items-center text-gray-700 dark:text-gray-400" href="#"
                    @click.prevent="dropdownOpen = ! dropdownOpen">
                    <span class="mr-3 h-11 w-11 overflow-hidden rounded-full">


                        <div
                            class="img_admin hover:text-dark-900 relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                        </div>
                    </span>

                    <!-- <span
                        class="text-theme-sm mr-1 block font-medium"><?php echo htmlspecialchars($fetchname['name']); ?></span> -->

                    <i :class="dropdownOpen && 'rotate-180'"
                        class="bi bi-chevron-down stroke-gray-500 dark:stroke-gray-400" style="font-size:18px;"></i>
                </a>

                <div x-show="dropdownOpen"
                    class="shadow-theme-lg dark:bg-gray-dark absolute right-0 mt-[17px] flex w-[260px] flex-col rounded-2xl border border-gray-200 bg-white p-3 dark:border-gray-800">
                    <div>
                        <span class="text-theme-sm block font-medium text-gray-700 dark:text-gray-400">
                            <!-- <?php echo htmlspecialchars($fetchname['name']); ?></span> -->
                            <span class="text-theme-xs mt-0.5 block text-gray-500 dark:text-gray-400">
                                <a href="/cdn-cgi/l/email-protection" class="__cf_email__"
                                    data-cfemail="fd8f9c93999290888e988fbd8d94909792d39e9290">[email&#160;protected]</a>
                            </span>
                    </div>

                    <button
                        class="group text-theme-sm mt-3 flex items-center gap-3 rounded-lg px-3 py-2 font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
                        <a href="./auth/logout.php" class="flex items-center gap-3">
                            <i class="bi bi-box-arrow-right fill-gray-500 group-hover:fill-gray-700 dark:group-hover:fill-gray-300"
                                style="font-size:24px;"></i>
                            Sign out
                        </a>
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>