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

            <button
                class="z-99999 flex h-10 w-10 items-center justify-center rounded-lg text-gray-700 hover:bg-gray-100 xl:hidden dark:text-gray-400 dark:hover:bg-gray-800"
                :class="menuToggle ? 'bg-gray-100 dark:bg-gray-800' : ''" @click.stop="menuToggle = !menuToggle">
                <i class="bi bi-three-dots-vertical" style="font-size:24px;"></i>
            </button>
        </div>

        <div :class="menuToggle ? 'flex' : 'hidden'"
            class="shadow-theme-md w-full items-center justify-between gap-4 px-5 py-4 xl:flex xl:justify-end xl:px-0 xl:shadow-none">
            <div class="2xsm:gap-3 flex items-center gap-2">
                <button
                    class="hover:text-dark-900 relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                    <a href="./auth/logout.php" class="flex items-center gap-3">
                        <i class="bi bi-box-arrow-right fill-gray-500 group-hover:fill-gray-700 dark:group-hover:fill-gray-300"
                            style="font-size:24px;"></i>
                    </a>
                </button>

                <button
                    class="hover:text-dark-900 relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                    @click.prevent="darkMode = !darkMode">
                    <i class="bi bi-moon-fill dark:block hidden" style="font-size:20px;"></i>
                    <i class="bi bi-sun-fill dark:hidden" style="font-size:20px;"></i>
                </button>
            </div>
        </div>
    </div>
</header>