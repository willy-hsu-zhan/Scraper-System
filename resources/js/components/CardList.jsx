import React, { useState } from 'react';
import Card from './Card';

export default function CardList({ items = [] }) {
    const categories = ['all', 'youtube', 'rent', 'game', 'coupon'];
    const [activeCategory, setActiveCategory] = useState('all');

    const filteredItems = activeCategory === 'all'
        ? items
        : items.filter(item => item.type === activeCategory);

    return (
        <div>
            {/* 分類按鈕 */}
            <div className="flex justify-center flex-wrap gap-2 mb-6">
                {categories.map(cat => (
                    <button
                        key={cat}
                        onClick={() => setActiveCategory(cat)}
                        className={`px-4 py-1.5 rounded-full text-sm transition
                            ${activeCategory === cat
                                ? 'bg-blue-500 text-white shadow'
                                : 'bg-gray-200 hover:bg-gray-300'
                            }`}
                    >
                        {cat}
                    </button>
                ))}
            </div>

            {/* 卡片 */}
            <div className="grid gap-4">
                {filteredItems.map((item, i) => (
                    <Card key={i} item={item} />
                ))}
            </div>
        </div>
    );
}
