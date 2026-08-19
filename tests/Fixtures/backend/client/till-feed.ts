/**
 * The browser half of the till feed: it dials the socket the server named, and treats a blank
 * socket as "this shop has none".
 */
export function dial(feed: TillFeed): Transport {
    if (feed.socket === '') {
        return polling(feed.channel, feed.pollMs);
    }

    return socketed(feed.socket);
}
