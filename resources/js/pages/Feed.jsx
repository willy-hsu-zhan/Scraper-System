// resources/js/pages/Feed.jsx
import React, { useEffect, useState } from 'react';
import ReactDOM from 'react-dom/client';
import axios from 'axios';
import '../../css/app.css';
import Card from '../components/Card';
import CardList from '../components/CardList';

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
            <CardList items={feed} />
        </div>
    );
}

// 指定掛載 DOM id
const rootEl = document.getElementById('feed-app');
if (rootEl) ReactDOM.createRoot(rootEl).render(<FeedApp />);
