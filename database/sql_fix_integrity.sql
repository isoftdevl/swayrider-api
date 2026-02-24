-- SwiftRider Database Integrity Fix
-- This script fixes foreign keys incorrectly pointing to 'users_backup'

-- 1. Deliveries table
ALTER TABLE `deliveries` DROP FOREIGN KEY `deliveries_user_id_foreign`;
ALTER TABLE `deliveries` ADD CONSTRAINT `deliveries_user_id_foreign` 
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- 2. Payment Methods table
ALTER TABLE `payment_methods` DROP FOREIGN KEY `payment_methods_user_id_foreign`;
ALTER TABLE `payment_methods` ADD CONSTRAINT `payment_methods_user_id_foreign` 
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- 3. Ratings table
ALTER TABLE `ratings` DROP FOREIGN KEY `ratings_user_id_foreign`;
ALTER TABLE `ratings` ADD CONSTRAINT `ratings_user_id_foreign` 
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- 4. Promo Code Usage table
ALTER TABLE `promo_code_usage` DROP FOREIGN KEY `promo_code_usage_user_id_foreign`;
ALTER TABLE `promo_code_usage` ADD CONSTRAINT `promo_code_usage_user_id_foreign` 
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- 5. Saved Addresses table
ALTER TABLE `saved_addresses` DROP FOREIGN KEY `saved_addresses_user_id_foreign`;
ALTER TABLE `saved_addresses` ADD CONSTRAINT `saved_addresses_user_id_foreign` 
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
