// assets/js/recommendations.js - Smart Product Recommendations Widget

class ProductRecommendations {
    constructor() {
        this.apiEndpoint = '/outsourced/api/v1/recommendations.php';
        this.token = localStorage.getItem('auth_token');
        this.sessionId = this.getSessionId();
        this.viewStartTime = null;
    }

    getSessionId() {
        let sessionId = sessionStorage.getItem('view_session_id');
        if (!sessionId) {
            sessionId = 's_' + Math.random().toString(36).substr(2, 9) + Date.now();
            sessionStorage.setItem('view_session_id', sessionId);
        }
        return sessionId;
    }

    async getRecommendations(type = 'personalized', limit = 10, productId = null, categoryId = null) {
        try {
            let url = `${this.apiEndpoint}?type=${type}&limit=${limit}`;
            if (productId) {
                url += `&product_id=${productId}`;
            }
            if (categoryId) {
                url += `&category_id=${categoryId}`;
            }

            const response = await fetch(url, {
                headers: {
                    'Authorization': this.token ? `Bearer ${this.token}` : ''
                }
            });

            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Failed to get recommendations:', error);
            return { success: false, data: [] };
        }
    }

    async logInteraction(productId, interactionType, additionalData = {}) {
        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': this.token ? `Bearer ${this.token}` : ''
                },
                body: JSON.stringify({
                    product_id: productId,
                    interaction_type: interactionType,
                    session_id: this.sessionId,
                    ...additionalData
                })
            });

            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Failed to log interaction:', error);
            return { success: false };
        }
    }

    async updateViewDuration(viewId, duration) {
        try {
            await fetch(this.apiEndpoint, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': this.token ? `Bearer ${this.token}` : ''
                },
                body: JSON.stringify({
                    view_id: viewId,
                    view_duration: duration
                })
            });
        } catch (error) {
            console.error('Failed to update view duration:', error);
        }
    }

    async trackProductView(productId, price) {
        return this.logInteraction(productId, 'view', { price });
    }

    async trackAddToCart(productId, price) {
        return this.logInteraction(productId, 'add_to_cart', { price });
    }

    async trackPurchase(productId, price) {
        return this.logInteraction(productId, 'purchase', { price });
    }

    async trackWishlist(productId) {
        return this.logInteraction(productId, 'wishlist');
    }

    async trackCompare(productId) {
        return this.logInteraction(productId, 'compare');
    }

    async trackReview(productId, rating) {
        return this.logInteraction(productId, 'review', { rating });
    }

    // Start tracking view time
    startViewTracking() {
        this.viewStartTime = Date.now();
    }

    // End tracking and send duration
    endViewTracking(viewId) {
        if (this.viewStartTime) {
            const duration = Math.floor((Date.now() - this.viewStartTime) / 1000);
            this.updateViewDuration(viewId, duration);
            this.viewStartTime = null;
        }
    }

    renderRecommendations(containerId, recommendations, options = {}) {
        const container = document.getElementById(containerId);
        if (!container) return;

        if (!recommendations || recommendations.length === 0) {
            container.innerHTML = options.emptyMessage || '<p class="text-center text-muted py-4">No recommendations available yet. Browse our products to get personalized suggestions!</p>';
            container.style.display = 'block';
            return;
        }

        container.style.display = '';

        const {
            title = 'Recommended for You',
            showPrice = true,
            showAddToCart = true,
            columns = 4,
            showScore = false,
            cardClass = ''
        } = options;

        let html = `
            <div class="recommendations-section">
                ${title ? `<h3 class="mb-4">${title}</h3>` : ''}
                <div class="row g-3">
        `;

        recommendations.forEach(product => {
            const productUrl = `/outsourced/public/product.php?id=${product.id}`;
            const imageUrl = product.image || '/outsourced/assets/images/products/placeholder.jpg';
            const price = typeof product.price === 'number' ? product.price.toFixed(2) : parseFloat(product.price || 0).toFixed(2);

            html += `
                <div class="col-${12/columns}">
                    <div class="card h-100 product-card ${cardClass}" data-product-id="${product.id}">
                        <a href="${productUrl}">
                            <img src="${imageUrl}" alt="${product.name}" class="card-img-top" style="height: 200px; object-fit: cover;">
                        </a>
                        <div class="card-body">
                            <h6 class="card-title">
                                <a href="${productUrl}">${this.truncateText(product.name, 50)}</a>
                            </h6>
            `;

            if (showPrice) {
                html += `<p class="price fw-bold text-primary">KES ${price}</p>`;
            }

            if (showScore && product.score) {
                html += `<small class="text-muted">Match: ${Math.round(product.score * 100)}%</small>`;
            }

            if (showAddToCart) {
                html += `
                    <button class="btn btn-sm btn-outline-primary w-100 mt-2" onclick="addToCart(${product.id}); event.preventDefault();">
                        Add to Cart
                    </button>
                `;
            }

            html += `</div></div></div>`;
        });

        html += `</div></div>`;
        container.innerHTML = html;
    }

    truncateText(text, maxLength) {
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    }

    async loadAndRenderRecommendations(containerId, options = {}) {
        const container = document.getElementById(containerId);
        try {
            const data = await this.getRecommendations(
                options.type || 'personalized',
                options.limit || 10,
                options.productId || null,
                options.categoryId || null
            );

            if (data.success) {
                this.renderRecommendations(containerId, data.data, options);
            } else {
                // Show fallback message
                container.innerHTML = '<p class="text-center text-muted py-4">No recommendations available. Browse our products to get personalized suggestions!</p>';
            }
        } catch (error) {
            console.error('Error loading recommendations:', error);
            container.innerHTML = '<p class="text-center text-muted py-4">Unable to load recommendations. Please try again later.</p>';
        }
    }

    // Load multiple recommendation types at once
    async loadAllRecommendationSections() {
        console.log('Loading recommendation sections...');
        const sections = [
            { id: 'recommended-for-you', type: 'personalized', title: 'Recommended for You', limit: 8 },
            { id: 'trending-now', type: 'trending', title: 'Trending Now', limit: 8 },
            { id: 'frequently-viewed', type: 'frequently_viewed', limit: 4 },
            { id: 'browsing-based', type: 'browsing_based', title: 'Based on Your Browsing', limit: 8 }
        ];

        for (const section of sections) {
            const container = document.getElementById(section.id);
            console.log('Checking section:', section.id, 'container exists:', !!container);
            if (container) {
                await this.loadAndRenderRecommendations(section.id, {
                    type: section.type,
                    limit: section.limit,
                    title: section.title,
                    showScore: section.type === 'browsing_based'
                });
            }
        }
    }

    // Load recommendations for a product page
    async loadProductPageRecommendations(productId) {
        const sections = [
            { 
                id: 'frequently-bought-together', 
                type: 'frequently_bought', 
                title: 'Customers Also Bought',
                productId: productId,
                limit: 4 
            },
            { 
                id: 'frequently-viewed-together', 
                type: 'frequently_viewed', 
                title: 'Frequently Viewed Together',
                productId: productId,
                limit: 4 
            },
            { 
                id: 'similar-products', 
                type: 'similar', 
                title: 'Similar Products',
                productId: productId,
                limit: 4 
            }
        ];

        for (const section of sections) {
            const container = document.getElementById(section.id);
            if (container) {
                await this.loadAndRenderRecommendations(section.id, {
                    type: section.type,
                    limit: section.limit,
                    title: section.title,
                    productId: section.productId
                });
            }
        }
    }
}

// Global instance
const productRecommendations = new ProductRecommendations();

// Auto-track product views on product page
document.addEventListener('DOMContentLoaded', () => {
    console.log('Recommendations JS loaded');
    console.log('Pathname:', window.location.pathname);
    
    // Check if we're on a product page
    const productId = document.querySelector('[data-product-id]')?.dataset.productId 
        || new URLSearchParams(window.location.search).get('id');

    if (productId && window.location.pathname.includes('product.php')) {
        // Get product price
        const priceElement = document.querySelector('.product-price, [data-price]');
        const price = priceElement?.dataset?.price || priceElement?.textContent?.replace(/[KES,]/g, '') || 0;

        // Track view and start timing
        productRecommendations.startViewTracking();
        productRecommendations.trackProductView(productId, price);

        // Also load product-specific recommendations
        productRecommendations.loadProductPageRecommendations(productId);
    } else if (window.location.pathname.includes('index.php') || window.location.pathname === '/outsourced/' || window.location.pathname === '/') {
        console.log('Loading homepage recommendations');
        // Homepage - load all recommendation sections
        productRecommendations.loadAllRecommendationSections();
    } else {
        console.log('Not on homepage or product page, skipping recommendations');
    }
});

// Track view duration when leaving product page
window.addEventListener('beforeunload', () => {
    if (productRecommendations.viewStartTime) {
        productRecommendations.endViewTracking(null); // View ID is tracked server-side
    }
});

// Also track when user navigates away
window.addEventListener('pagehide', () => {
    if (productRecommendations.viewStartTime) {
        // Send beacon for reliable tracking
        navigator.sendBeacon(
            productRecommendations.apiEndpoint,
            JSON.stringify({
                type: 'view_end',
                session_id: productRecommendations.sessionId
            })
        );
    }
});

// Export for use in other scripts
window.ProductRecommendations = ProductRecommendations;
window.productRecommendations = productRecommendations;
