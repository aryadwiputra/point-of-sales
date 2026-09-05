/**
 * Get proper image URL - handles both full URLs and filenames
 * @param {string} image - Image path (can be filename or full URL)
 * @param {string} folder - Storage folder (products, categories, etc)
 * @returns {string|null} - Proper image URL or null
 */
export function getImageUrl(image, folder = "products") {
    if (!image) return null;

    // If already a full URL, return as-is
    if (
        image.startsWith("http://") ||
        image.startsWith("https://") ||
        image.startsWith("/storage/")
    ) {
        return image;
    }

    // Otherwise, prepend storage path
    return `/storage/${folder}/${image}`;
}

/**
 * Get product image URL
 * @param {string} image - Product image
 * @returns {string|null}
 */
export function getProductImageUrl(image) {
    return getImageUrl(image, "products");
}
