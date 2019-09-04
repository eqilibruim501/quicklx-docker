/*!
 * remark (http://getbootstrapadmin.com/remark)
 * Copyright 2017 amazingsurge
 * Licensed under the Themeforest Standard Licenses
 */

define(["exports","jquery","./Component"],function(exports,_jquery,_Component2){"use strict";Object.defineProperty(exports,"__esModule",{value:!0});var _jquery2=babelHelpers.interopRequireDefault(_jquery),_Component3=babelHelpers.interopRequireDefault(_Component2),$BODY=(0,_jquery2.default)("body"),$HTML=(0,_jquery2.default)("html"),Scrollable=function(){function Scrollable($el){babelHelpers.classCallCheck(this,Scrollable),this.$el=$el,this.api=null,this.init()}return babelHelpers.createClass(Scrollable,[{key:"init",value:function(){this.api=this.$el.asScrollable({namespace:"scrollable",skin:"scrollable-inverse",direction:"vertical",contentSelector:">",containerSelector:">"}).data("asScrollable")}},{key:"update",value:function(){this.api&&this.api.update()}},{key:"enable",value:function(){this.api||this.init(),this.api&&this.api.enable()}},{key:"disable",value:function(){this.api&&this.api.disable()}}]),Scrollable}(),_class=function(_Component){function _class(){var _ref;babelHelpers.classCallCheck(this,_class);for(var _len=arguments.length,args=Array(_len),_key=0;_key<_len;_key++)args[_key]=arguments[_key];var _this=babelHelpers.possibleConstructorReturn(this,(_ref=_class.__proto__||Object.getPrototypeOf(_class)).call.apply(_ref,[this].concat(args)));return _this.scrollable=new Scrollable(_this.$el),_this}return babelHelpers.inherits(_class,_Component),babelHelpers.createClass(_class,[{key:"getDefaultState",value:function(){return{gridmenu:!1}}},{key:"getDefaultActions",value:function(){return{gridmenu:"toggle"}}},{key:"open",value:function(){this.animate(function(){this.$el.addClass("active"),(0,_jquery2.default)('[data-toggle="gridmenu"]').addClass("active").attr("aria-expanded",!0),$BODY.addClass("site-gridmenu-active"),$HTML.addClass("disable-scrolling")},function(){this.scrollable.enable()})}},{key:"close",value:function(){this.animate(function(){this.$el.removeClass("active"),(0,_jquery2.default)('[data-toggle="gridmenu"]').addClass("active").attr("aria-expanded",!0),$BODY.removeClass("site-gridmenu-active"),$HTML.removeClass("disable-scrolling")},function(){this.scrollable.disable()})}},{key:"toggle",value:function(opened){opened?this.open():this.close()}},{key:"animate",value:function(doing,callback){var _this2=this;doing.call(this),this.$el.trigger("changing.site.gridmenu"),setTimeout(function(){callback.call(_this2),_this2.$el.trigger("changed.site.gridmenu")},500)}}]),_class}(_Component3.default);exports.default=_class});