import React from 'react';

export default function Card({ item }) {

    const typeColor = {
        youtube: "bg-red-100 text-red-600",
        rent: "bg-blue-100 text-blue-600",
        game: "bg-green-100 text-green-600",
        coupon: "bg-yellow-100 text-yellow-700",
        default: "bg-gray-100 text-gray-600"
    };

    // 共用 Header
    const renderHeader = () => (
        <div className="flex items-center justify-between mb-2">
            <span className={`px-2 py-1 text-xs rounded-full ${typeColor[item.type] || typeColor.default}`}>
                {item.type}
            </span>

            {item.time && (
                <span className="text-xs text-gray-400">
                    {item.time}
                </span>
            )}
        </div>
    );

    // 外層 container
    const containerProps = {
        className: "bg-white rounded-2xl shadow-md p-4 hover:shadow-xl transition"
    };

    // 用 switch 決定內部內容
    let content;
    switch (item.type) {
        case 'coupon':
            content = (
                <>
                    {renderHeader()}
                    <h2 className="text-lg font-bold mb-1 text-yellow-700">{item.title}</h2>
                    <p className="text-gray-700 text-sm mb-3">{item.subtitle}</p>

                    {item.code && (
                        <div className="flex items-center justify-between bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2 mb-2">
                            <span className="font-mono text-sm">{item.code}</span>
                            <button
                                onClick={() => navigator.clipboard.writeText(item.code)}
                                className="text-xs text-blue-500 hover:underline"
                            >
                                複製
                            </button>
                        </div>
                    )}

                    {item.expire_date && (
                        <div className="text-xs text-gray-500 mb-2">⏰ 截止：{item.expire_date}</div>
                    )}

                    {item.link && (
                        <a
                            href={item.link}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-blue-500 text-sm hover:underline"
                        >
                            使用優惠 →
                        </a>
                    )}
                </>
            );
            break;

        case 'rent':
            content = (
                <>
                    {renderHeader()}
                    <h2 className="text-lg font-bold mb-1">{item.title}</h2>
                    <p className="text-gray-500 text-sm">{item.subtitle}</p>
                    {item.price_text && (
                        <div className="text-blue-600 font-bold text-xl mt-2">{item.price_text}</div>
                    )}
                    {item.link && (
                        <a
                            href={item.link}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="block mt-2 text-sm text-blue-500 hover:underline"
                        >
                            查看房源 →
                        </a>
                    )}
                </>
            );
            break;

        default: // youtube / game / 其他
            content = (
                <>
                    {renderHeader()}
                    <h2 className="text-lg font-bold mb-1">{item.title}</h2>
                    <p className="text-gray-600 text-sm mb-2">{item.subtitle}</p>

                    {item.type === 'youtube' && item.embed_url && (
                        <div className="mb-2">
                            <iframe
                                className="w-full aspect-video rounded-lg"
                                src={item.embed_url}
                                title={item.title}
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowFullScreen
                            ></iframe>
                        </div>
                    )}

                    {item.link && item.type !== 'youtube' && (
                        <a
                            href={item.link}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-blue-500 text-sm hover:underline"
                        >
                            查看詳情 →
                        </a>
                    )}
                </>
            );
    }

    return <div {...containerProps}>{content}</div>;
}
