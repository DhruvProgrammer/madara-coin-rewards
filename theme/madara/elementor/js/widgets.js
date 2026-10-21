class ElementorMangaSliderClass extends elementorModules.frontend.handlers.Base {
    getDefaultSettings() { }
    getDefaultElements() {
        return {
            widget: this.$element.find('.slider__container')[0]
        }
    }
    bindEvents() {
        // init slider       
        var $this = jQuery(this.elements.widget);
        var $parent = $this.parents(".manga-slider");
        var style = $parent.attr('data-style');

        var autoplay = $parent.attr('data-autoplay');
        autoplay = autoplay == "1" ? true : false;
        var manga_slidesToShow = parseInt($parent.attr('data-count'));
        var check_style = $this.parents(".style-3").length;
        var check_rtl = (jQuery("body").css('direction') === "rtl");
        var manga_style_1 = {
            dots: $parent.hasClass('dots-off') ? false : true,
            infinite: true,
            speed: 500,
            centerMode: (((manga_slidesToShow % 2 !== 0) && (!check_style)) ? true : false),
            slidesToShow: manga_slidesToShow,
            slidesToScroll: 1,
            arrows: false,
            rtl: check_rtl,
            autoplay: autoplay,
            responsive: [{
                breakpoint: 992,
                settings: {
                    slidesToShow: (manga_slidesToShow == 1) ? 1 : 2,
                    slidesToScroll: 1,
                    infinite: true,
                    centerMode: false,
                    dots: $parent.hasClass('dots-off') ? false : true,
                }
            }, {
                breakpoint: 660,
                settings: {
                    slidesToShow: 1,
                    slidesToScroll: 1,
                    infinite: true,
                    variableWidth: false,
                    dots: $parent.hasClass('dots-off') ? false : true,
                }
            }, {
                breakpoint: 480,
                settings: {
                    slidesToShow: 1,
                    variableWidth: false,
                }
            }]
        }
        var manga_style_2 = {
            dots: $parent.hasClass('dots-off') ? false : true,
            infinite: true,
            speed: 500,
            slidesToShow: manga_slidesToShow,
            slidesToScroll: 1,
            arrows: false,
            rtl: check_rtl,
            autoplay: autoplay,
            responsive: [{
                breakpoint: 992,
                settings: {
                    slidesToShow: (manga_slidesToShow == 1) ? 1 : 2,
                    slidesToScroll: 1,
                    infinite: true,
                    dots: $parent.hasClass('dots-off') ? false : true,
                }
            }, {
                breakpoint: 768,
                settings: {
                    slidesToShow: 1,
                    slidesToScroll: 1,
                    infinite: true,
                    dots: $parent.hasClass('dots-off') ? false : true,
                }
            }]
        }
        var manga_style_3 = {
            dots: $parent.hasClass('dots-off') ? false : true,
            infinite: true,
            speed: 500,
            slidesToShow: manga_slidesToShow,
            slidesToScroll: 1,
            arrows: false,
            rtl: check_rtl,
            autoplay: autoplay,
            responsive: [{
                breakpoint: 992,
                settings: {
                    slidesToShow: (manga_slidesToShow == 1) ? 1 : 2,
                    slidesToScroll: 1,
                    infinite: true,
                    dots: $parent.hasClass('dots-off') ? false : true,
                }
            }, {
                breakpoint: 768,
                settings: {
                    slidesToShow: 1,
                    slidesToScroll: 1,
                    infinite: true,
                    dots: $parent.hasClass('dots-off') ? false : true,
                }
            }]
        }

        switch (style) {
            case 'style-1':
                $this.slick(manga_style_1);
                break;
            case 'style-2':
                $this.slick(manga_style_2);
                break;
            case 'style-3':
            case 'style-4':
                $this.slick(manga_style_3);
                break;
        }
    }
}

jQuery(window).on('elementor/frontend/init', () => {
    const addMadaraElementorHandler = ($element) => {
        elementorFrontend.elementsHandler.addHandler(ElementorMangaSliderClass, {
            $element,
        });
    };

    elementorFrontend.hooks.addAction('frontend/element_ready/manga-slider.default', addMadaraElementorHandler);
    elementorFrontend.hooks.addAction('frontend/element_ready/global', addMadaraElementorHandler);
});


class ElementorMangaPopularSliderClass extends elementorModules.frontend.handlers.Base {
    getDefaultSettings() { }
    getDefaultElements() {
        return {
            widget: this.$element.find('.slider__container')[0]
        }
    }
    bindEvents() {
        // init slider       
        var $this = jQuery(this.elements.widget);

        var manga_slidesToShow = parseInt($this.parents(".popular-slider").attr('data-count'));
        var check_rtl = (jQuery("body").css('direction') === "rtl");
        var autoplay = $this.parents(".popular-slider").attr('data-autoplay');
        autoplay = autoplay == "1" ? true : false;
        var popular_style_2 = {
            dots: false,
            infinite: true,
            speed: 500,
            slidesToShow: manga_slidesToShow,
            arrows: true,
            rtl: check_rtl,
            autoplay: autoplay,
            slidesToScroll: 1,
            responsive: [
                {
                    breakpoint: 1700,
                    settings: {
                        slidesToShow: (manga_slidesToShow == 1) ? 1 : 4,
                        slidesToScroll: 1,
                    }
                },
                {
                    breakpoint: 1400,
                    settings: {
                        slidesToShow: (manga_slidesToShow == 1) ? 1 : 3,
                        slidesToScroll: 1,
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: (manga_slidesToShow == 1) ? 1 : 2,
                        slidesToScroll: 1,
                    }
                },
                {
                    breakpoint: 600,
                    settings: {
                        slidesToShow: 1,
                        slidesToScroll: 1,
                    }
                },
            ]
        }
        var popular_style_1 = {
            dots: false,
            infinite: true,
            speed: 500,
            slidesToShow: manga_slidesToShow,
            arrows: true,
            rtl: check_rtl,
            autoplay: autoplay,
            slidesToScroll: 1,
            responsive: [
                {
                    breakpoint: 1700,
                    settings: {
                        slidesToShow: manga_slidesToShow,
                        slidesToScroll: 1,
                    }
                },
                {
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: manga_slidesToShow > 3 ? 3 : manga_slidesToShow,
                        slidesToScroll: 1,
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: manga_slidesToShow > 3 ? 3 : manga_slidesToShow,
                        slidesToScroll: 1,
                    }
                },
                {
                    breakpoint: 600,
                    settings: {
                        slidesToShow: manga_slidesToShow > 3 ? 3 : manga_slidesToShow,
                        slidesToScroll: 1,
                    }
                },
            ]
        }

        var style = $this.parents(".popular-slider").attr('data-style');
        switch (style) {
            case 'style-1':
                $this.slick(popular_style_1);
                break;
            case 'style-2':
                $this.slick(popular_style_2);
                break;
        }
    }
}

jQuery(window).on('elementor/frontend/init', () => {
    const addMadaraElementorHandler = ($element) => {
        elementorFrontend.elementsHandler.addHandler(ElementorMangaPopularSliderClass, {
            $element,
        });

        jQuery.ajax({
            url: manga.ajax_url,
            type: 'GET',
            data: {
                action: 'guest_histories',
                count: 5
            },
            success: function (html) {
                if (html && html != "0") {
                    jQuery('.widget-elementor .my-history').html(html);
                } else {
                    jQuery('.widget-elementor .no-histories').show();
                }
            },
            complete: function (e) {

            }
        });
    };

    elementorFrontend.hooks.addAction('frontend/element_ready/popular-slider.default', addMadaraElementorHandler);
    elementorFrontend.hooks.addAction('frontend/element_ready/global', addMadaraElementorHandler);
});

