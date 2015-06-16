
$class("Pagebar", [kx.Weblet, kx.EventMixin], 
{
	_templateFile: "pagebar.html",

	_currentPage: 1,

	__constructor: function() {
	},

	onCreated: function(domNode) {
		domNode.find("li").bind("click", kx.bind(this, "pageClicked"));
	},

	pageClicked: function(e) {
		this._currentPage = $(e.delegateTarget).text();
		this.fireEvent("page-changed", this._currentPage);
	},

	currentPage: function() {
		return this._currentPage;
	}

});