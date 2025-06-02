document.addEventListener('DOMContentLoaded', async () => {
    const newsContainer = document.getElementById('news-container');
    if (!newsContainer) return;
    
    try {
        // Use NewsAPI or similar service (you'll need an API key)
        const apiKey = 'YOUR_NEWS_API_KEY'; // Replace with actual key
        const response = await fetch(`https://newsapi.org/v2/everything?q=recycling OR sustainability&language=en&sortBy=publishedAt&apiKey=${apiKey}`);
        const data = await response.json();
        
        if (data.articles && data.articles.length > 0) {
            newsContainer.innerHTML = data.articles.slice(0, 3).map(article => `
                <div class="news-card">
                    <div class="news-image" style="background-image: url('${article.urlToImage || 'assets/default-news.jpg'}')"></div>
                    <div class="news-content">
                        <h3><a href="${article.url}" target="_blank">${article.title}</a></h3>
                        <p>${article.description || ''}</p>
                        <div class="news-meta">
                            <span class="source">${article.source.name}</span>
                            <span class="date">${new Date(article.publishedAt).toLocaleDateString()}</span>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            newsContainer.innerHTML = '<p>No recent news found. Check back later.</p>';
        }
    } catch (error) {
        console.error("Failed to load news:", error);
        newsContainer.innerHTML = '<p>Could not load news at this time.</p>';
    }
});
