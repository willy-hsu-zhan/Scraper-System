// resources/js/pages/Feed.jsx
import React, { useEffect, useState } from 'react';
import ReactDOM from 'react-dom/client';
import axios from 'axios';
import '../../css/app.css';
import Card from '../components/Card';

function FeedApp() {
    const [feed, setFeed] = useState([]);

    useEffect(() => {
        axios.get('/api/feed')
            .then(res => setFeed(res.data))
            .catch(err => console.error(err));
    }, []);

    return (
        <div className="max-w-xl mx-auto py-6 px-4">
            <h1 className="text-2xl font-bold mb-6 text-center">🔥 最新資訊</h1>
            <div className="space-y-4">
                {feed.map((item, index) => <Card key={index} item={item} />)}
            </div>
        </div>
    );
}

// 指定掛載 DOM id
const rootEl = document.getElementById('feed-app');
if (rootEl) ReactDOM.createRoot(rootEl).render(<FeedApp />);
