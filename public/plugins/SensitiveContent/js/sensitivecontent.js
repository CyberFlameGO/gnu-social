window.buildAttachmentHTML = function (attachments) {
    let attachmentHTML = '';
    let oembedHTML = '';
    const quotedNotices = [];
    let attachmentNum = 0;
    let oembedNum = 0;
    const urlsToHide = [];
    if (typeof attachments != "undefined") {
        $.each(attachments, function () {

            // quoted notices
            if (typeof this.quoted_notice != 'undefined') {

                let quotedContent = this.quoted_notice.content;

                // quoted notice's attachments' thumb urls
                let quotedAttachmentsHTML = '';
                let quotedAttachmentsHTMLbefore = '';
                let quotedAttachmentsHTMLafter = '';
                if (typeof this.quoted_notice.attachments != 'undefined' && this.quoted_notice.attachments.length > 0) {
                    quotedAttachmentsHTML += '<div class="quoted-notice-attachments quoted-notice-attachments-num-' + this.quoted_notice.attachments.length + '">';
                    $.each(this.quoted_notice.attachments, function (k, qAttach) {
                        quotedAttachmentsHTML += '<div class="quoted-notice-img-container" style="background-image:url(\'' + qAttach.thumb_url + '\')"><img class="quoted-notice-img" src="' + qAttach.thumb_url + '" /></div>';
                        // remove attachment string from content
                        quotedContent = quotedContent.split(window.siteInstanceURL + 'attachment/' + qAttach.attachment_id).join('');
                    });
                    quotedAttachmentsHTML += '</div>';

                    // if there is only one attachment, it goes before, otherwise after
                    if (this.quoted_notice.attachments.length == 1) {
                        quotedAttachmentsHTMLbefore = quotedAttachmentsHTML;
                    } else {
                        quotedAttachmentsHTMLafter = quotedAttachmentsHTML;
                    }
                }

                const quotedNoticeHTML = quotedAttachmentsHTMLbefore + '\
										<div class="quoted-notice-header">\
											<span class="quoted-notice-author-fullname">' + this.quoted_notice.fullname + '</span>\
											<span class="quoted-notice-author-nickname">' + this.quoted_notice.nickname + '</span>\
										</div>\
										<div class="quoted-notice-body">' + $.trim(quotedContent) + '</div>\
										' + quotedAttachmentsHTMLafter;

                quotedNotices.push({
                    url: this.url,
                    html: quotedNoticeHTML,
                    href: window.siteInstanceURL + 'notice/' + this.quoted_notice.id,
                    class: 'quoted-notice'
                });
            }

            // if we have Twitter oembed data, we add is as quotes
            else if (typeof this.oembed != 'undefined'
                && this.oembed !== false
                && this.oembed.provider == 'Twitter') {

                const twitterHTML = '<div class="oembed-item-header">\
										<span class="oembed-item-title">' + this.oembed.author_name + '</span>\
										<span class="oembed-username">' + this.oembed.title + '</span>\
									</div>\
									<div class="oembed-item-body">' + this.oembed.oembedHTML + '</div>\
									<div class="oembed-item-footer">\
										<span class="oembed-item-provider">' + this.oembed.provider + '</span>\
									</div>';
                quotedNotices.push({
                    url: this.url,
                    html: twitterHTML,
                    href: this.url,
                    class: 'oembed-item'
                });
            }
            // if we have other oembed data (but not for photos and youtube, we handle those later)
            else if (typeof this.oembed != 'undefined'
                && this.oembed !== false
                && this.oembed.title !== null
                && this.oembed.provider != 'YouTube'
                && this.oembed.provider != 'Vimeo'
                && this.oembed.type != 'photo') {

                let oembedImage = '';
                // only local images
                if (typeof this.thumb_url != 'undefined'
                    && this.thumb_url !== null
                    && isLocalURL(this.thumb_url)) {
                    oembedImage = '<div class="oembed-img-container" style="background-image:url(\'' + this.thumb_url + '\')"><img class="oembed-img" src="' + this.thumb_url + '" /></div>';
                }

                let oembedBody = '';

                // don't add body if html it's too similar (80%) to the title (wordpress does this..)
                if (this.oembed.oembedHTML !== null
                    && this.oembed.oembedHTML.length > 0) {
                    if (this.oembed.oembedHTML.length > 200) {
                        this.oembed.oembedHTML = this.oembed.oembedHTML.substring(0, 200) + '???????';
                    }
                    if (stringSimilarity(this.oembed.oembedHTML, this.oembed.title.substring(0, 200)) < 80) {
                        oembedBody = this.oembed.oembedHTML;
                    }
                }

                if (this.oembed.provider === null) {
                    var oembedProvider = this.url;
                    var oembedProviderURL = '';
                } else {
                    var oembedProvider = this.oembed.provider;
                    var oembedProviderURL = removeProtocolFromUrl(this.oembed.provider_url);
                    // remove trailing /
                    if (oembedProviderURL.slice(-1) == '/') {
                        oembedProviderURL = oembedProviderURL.slice(0, -1);
                    }
                }

                // If the oembed data is generated by Qvitter, we know a better way of showing the title
                const oembedTitle = this.oembed.title;
                let oembedTitleHTML = '<span class="oembed-item-title">' + oembedTitle + '</span>';
                if (oembedTitle.slice(-10) == ' (Qvitter)') {
                    const oembedTimePosted = parseTwitterLongDate(oembedTitle.slice(0, -10));
                    const oembedGNUsocialUsername = this.oembed.author_name.substring(this.oembed.author_name.lastIndexOf('(') + 1, this.oembed.author_name.lastIndexOf(')'));
                    const oembedGNUsocialFullname = this.oembed.author_name.slice(0, -(oembedGNUsocialUsername.length + 3));
                    oembedTitleHTML = '<span class="oembed-item-title">' + oembedGNUsocialFullname + '</span>\
										<span class="oembed-username">@' + oembedGNUsocialUsername + '</span>';
                }


                oembedHTML += '<a href="' + this.url + '" class="oembed-item">\
									' + oembedImage + '\
									<div class="oembed-item-header">\
										' + oembedTitleHTML + '\
									</div>\
									<div class="oembed-item-body">' + oembedBody + '</div>\
									<div class="oembed-item-footer">\
										<span class="oembed-item-provider">' + oembedProvider + '</span>\
										<span class="oembed-item-provider-url">' + oembedProviderURL + '</span>\
									</div>\
								 </a>';
                oembedNum++;
            }
            // if there's a local thumb_url we assume this is a image or video
            else if (typeof this.thumb_url != 'undefined'
                && this.thumb_url !== null
                && isLocalURL(this.thumb_url)) {
                let bigThumbW = 1000;
                let bigThumbH = 3000;
                if (bigThumbW > window.siteMaxThumbnailSize) {
                    bigThumbW = window.siteMaxThumbnailSize;
                }
                if (bigThumbH > window.siteMaxThumbnailSize) {
                    bigThumbH = window.siteMaxThumbnailSize;
                }

                // very long landscape images should not have background-size:cover
                let noCoverClass = '';
                if (this.width / this.height > 2) {
                    noCoverClass = ' no-cover';
                }

                // play button for videos and animated gifs
                let playButtonClass = '';
                if (typeof this.animated != 'undefined' && this.animated === true
                    || (this.url.indexOf('://www.youtube.com') > -1 || this.url.indexOf('://youtu.be') > -1)
                    || this.url.indexOf('://vimeo.com') > -1) {
                    playButtonClass = ' play-button';
                }

                // gif-class
                var animatedGifClass = '';
                if (typeof this.animated != 'undefined' && this.animated === true) {
                    var animatedGifClass = ' animated-gif';
                }

                // animated gifs always get default small non-animated thumbnail
                if (this.animated === true) {
                    var img_url = this.thumb_url;
                }
                // if no dimensions are set, go with default thumb
                else if (this.width === null && this.height === null) {
                    var img_url = this.thumb_url;
                }
                // large images get large thumbnail
                else if (this.width > 1000) {
                    var img_url = this.large_thumb_url;
                }
                // no thumbnails for small local images
                else if (this.url.indexOf(window.siteInstanceURL) === 0) {
                    var img_url = this.url;
                }
                // small thumbnail for small remote images
                else {
                    var img_url = this.thumb_url;
                }

                var urlToHide = window.siteInstanceURL + 'attachment/' + this.id;

                attachmentHTML += '<a data-local-attachment-url="' + urlToHide + '" style="background-image:url(\'' + img_url + '\')" class="thumb-container' + noCoverClass + playButtonClass + animatedGifClass + ' ' + CSSclassNameByHostFromURL(this.url) + '" href="' + this.url + '"><img class="attachment-thumb" data-mime-type="' + this.mimetype + '" src="' + img_url + '"/ data-width="' + this.width + '" data-height="' + this.height + '" data-full-image-url="' + this.url + '" data-thumb-url="' + img_url + '"></a>';
                urlsToHide.push(urlToHide); // hide this attachment url from the queet text
                attachmentNum++;
            } else if (this.mimetype == 'image/svg+xml') {
                var urlToHide = window.siteInstanceURL + 'attachment/' + this.id;
                attachmentHTML += '<a data-local-attachment-url="' + urlToHide + '" style="background-image:url(\'' + this.url + '\')" class="thumb-container" href="' + this.url + '"><img class="attachment-thumb" data-mime-type="' + this.mimetype + '" src="' + this.url + '"/></a>';
                urlsToHide.push(urlToHide); // hide this attachment url from the queet text
                attachmentNum++;
            }
        });
    }
    return {
        html: '<div class="oembed-data oembed-num-' + oembedNum + '">' + oembedHTML + '</div><div class="queet-thumbs thumb-num-' + attachmentNum + '"><div class="sensitive-blocker">&nbsp;</div>' + attachmentHTML + '</div>',
        urlsToHide: urlsToHide,
        quotedNotices: quotedNotices
    };
};


window.sensitiveContentOriginalBuildQueetHtml = window.buildQueetHtml;

window.buildQueetHtml = function (obj, idInStream, extraClasses, requeeted_by, isConversation) {
    //add tags to json if they don't exit
    if (obj.tags && obj.tags.length > 0) {
        for (let tagI = 0; tagI < obj.tags.length; ++tagI) {
            extraClasses += (' data-tag-' + obj.tags[tagI]);
            if (window.loggedIn.hide_sensitive && obj.tags[tagI] === 'nsfw') extraClasses += ' sensitive-notice';
        }
    }

    return window.sensitiveContentOriginalBuildQueetHtml(obj, idInStream, extraClasses, requeeted_by, isConversation);
};