-- Database: `library_management`

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

START TRANSACTION;

SET time_zone = "+00:00";

-- --------------------------------------------------------

CREATE TABLE `Categories` (
    `category_id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    PRIMARY KEY (`category_id`),
    UNIQUE KEY `name` (`name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `Grades` (
    `grade_id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    PRIMARY KEY (`grade_id`),
    UNIQUE KEY `name` (`name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `Teachers` (
    `teacher_id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `region` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `subject` VARCHAR(255) DEFAULT NULL,
    `image_url` VARCHAR(500) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    PRIMARY KEY (`teacher_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `TeacherGrades` (
    `teacher_grade_id` INT NOT NULL AUTO_INCREMENT,
    `teacher_id` INT NOT NULL,
    `grade_id` INT NOT NULL,
    PRIMARY KEY (`teacher_grade_id`),
    UNIQUE KEY `unique_teacher_grade` (`teacher_id`, `grade_id`),
    CONSTRAINT `TeacherGrades_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `Teachers` (`teacher_id`) ON DELETE CASCADE,
    CONSTRAINT `TeacherGrades_ibfk_2` FOREIGN KEY (`grade_id`) REFERENCES `Grades` (`grade_id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `Books` (
    `book_id` INT NOT NULL AUTO_INCREMENT,
    `category_id` INT DEFAULT NULL,
    `grade_id` INT DEFAULT NULL,
    `teacher_id` INT DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 0,
    `price` DECIMAL(10, 2) NOT NULL,
    `image_url` VARCHAR(500) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    PRIMARY KEY (`book_id`),
    KEY `category_id` (`category_id`),
    KEY `grade_id` (`grade_id`),
    KEY `teacher_id` (`teacher_id`),
    CONSTRAINT `Books_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `Categories` (`category_id`) ON DELETE SET NULL,
    CONSTRAINT `Books_ibfk_2` FOREIGN KEY (`grade_id`) REFERENCES `Grades` (`grade_id`) ON DELETE SET NULL,
    CONSTRAINT `Books_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `Teachers` (`teacher_id`) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `Students` (
    `student_id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `address` VARCHAR(500) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `grade_id` INT DEFAULT NULL,
    `amount_paid` DECIMAL(10, 2) DEFAULT 0.00,
    `amount_due` DECIMAL(10, 2) DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    PRIMARY KEY (`student_id`),
    KEY `grade_id` (`grade_id`),
    CONSTRAINT `Students_ibfk_1` FOREIGN KEY (`grade_id`) REFERENCES `Grades` (`grade_id`) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `Users` (
    `user_id` INT NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM(
        'admin',
        'librarian',
        'teacher',
        'student'
    ) NOT NULL DEFAULT 'student',
    `is_verified` TINYINT(1) DEFAULT 0,
    `verification_token` VARCHAR(255) DEFAULT NULL,
    `verification_code` INT DEFAULT NULL,
    `token_expiry` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `BookReservations` (
    `reservation_id` INT NOT NULL AUTO_INCREMENT,
    `order_number` VARCHAR(20) NOT NULL,
    `student_id` INT NOT NULL,
    `teacher_id` INT DEFAULT NULL,
    `book_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `book_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `reservation_date` DATE NOT NULL DEFAULT CURRENT_DATE(),
    `approved_date` DATE DEFAULT NULL,
    `status` ENUM(
        'pending',
        'approved',
        'cancelled',
        'returned'
    ) DEFAULT 'pending',
    `due_date` DATE DEFAULT NULL,
    `return_date` DATE DEFAULT NULL,
    `amount_paid` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `amount_due` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `payment_proof` VARCHAR(255) DEFAULT NULL,
    `receipt_image` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    PRIMARY KEY (`reservation_id`),
    UNIQUE KEY `order_number` (`order_number`),
    KEY `student_id` (`student_id`),
    KEY `teacher_id` (`teacher_id`),
    KEY `book_id` (`book_id`),
    CONSTRAINT `BookReservations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `Students` (`student_id`) ON DELETE CASCADE,
    CONSTRAINT `BookReservations_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `Teachers` (`teacher_id`) ON DELETE SET NULL,
    CONSTRAINT `BookReservations_ibfk_3` FOREIGN KEY (`book_id`) REFERENCES `Books` (`book_id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `BookReviews` (
    `review_id` INT NOT NULL AUTO_INCREMENT,
    `book_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `rating` INT DEFAULT NULL CHECK (
        `rating` >= 1
        AND `rating` <= 5
    ),
    `review` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    PRIMARY KEY (`review_id`),
    KEY `book_id` (`book_id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `BookReviews_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `Books` (`book_id`) ON DELETE CASCADE,
    CONSTRAINT `BookReviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `Users` (`user_id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `PaymentTransactions` (
    `transaction_id` INT NOT NULL AUTO_INCREMENT,
    `reservation_id` INT NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `payment_date` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (`transaction_id`),
    KEY `reservation_id` (`reservation_id`),
    CONSTRAINT `PaymentTransactions_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `BookReservations` (`reservation_id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `LateReturns` (
    `late_return_id` INT NOT NULL AUTO_INCREMENT,
    `reservation_id` INT NOT NULL,
    `days_late` INT NOT NULL,
    `fine_amount` DECIMAL(10, 2) DEFAULT 0.00,
    `paid` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (`late_return_id`),
    KEY `reservation_id` (`reservation_id`),
    CONSTRAINT `LateReturns_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `BookReservations` (`reservation_id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `DailyStatistics` (
    `stat_date` DATE NOT NULL,
    `books_sold` INT DEFAULT 0,
    `total_orders` INT DEFAULT 0,
    `total_revenue` DECIMAL(15, 2) DEFAULT 0.00,
    `orders_pending` INT DEFAULT 0,
    `orders_approved` INT DEFAULT 0,
    `orders_cancelled` INT DEFAULT 0,
    `orders_returned` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    PRIMARY KEY (`stat_date`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `Notifications` (
    `notification_id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (`notification_id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `Notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `Users` (`user_id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `Logs` (
    `log_id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT DEFAULT NULL,
    `action` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `log_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (`log_id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `Logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `Users` (`user_id`) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

CREATE TABLE `UserLogins` (
    `login_id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `login_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    PRIMARY KEY (`login_id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `UserLogins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `Users` (`user_id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

COMMIT;