app = angular.module('app', ['ngRoute', 'ui.tms', 'matters.xxt', 'channel.fe.pl']);
app.config(['$controllerProvider', '$routeProvider', '$locationProvider', function($controllerProvider, $routeProvider, $locationProvider) {
	app.provider = {
		controller: $controllerProvider.register
	};
	$routeProvider.when('/rest/pl/fe/matter/enroll/schema', {
		templateUrl: '/views/default/pl/fe/matter/enroll/schema.html?_=1',
		controller: 'ctrlSchema',
		resolve: {
			load: function($q) {
				var defer = $q.defer();
				(function() {
					$.getScript('/views/default/pl/fe/matter/enroll/schema.js', function() {
						defer.resolve();
					});
				})();
				return defer.promise;
			}
		}
	}).when('/rest/pl/fe/matter/enroll/page', {
		templateUrl: '/views/default/pl/fe/matter/enroll/page.html?_=1',
		controller: 'ctrlPage',
		resolve: {
			load: function($q) {
				var defer = $q.defer();
				(function() {
					$.getScript('/views/default/pl/fe/matter/enroll/page.js', function() {
						defer.resolve();
					});
				})();
				return defer.promise;
			}
		}
	}).when('/rest/pl/fe/matter/enroll/preview', {
		templateUrl: '/views/default/pl/fe/matter/enroll/preview.html?_=1',
		controller: 'ctrlPreview',
		resolve: {
			load: function($q) {
				var defer = $q.defer();
				(function() {
					$.getScript('/views/default/pl/fe/matter/enroll/preview.js', function() {
						defer.resolve();
					});
				})();
				return defer.promise;
			}
		}
	}).when('/rest/pl/fe/matter/enroll/event', {
		templateUrl: '/views/default/pl/fe/matter/enroll/event.html?_=1',
		controller: 'ctrlEntry',
		resolve: {
			load: function($q) {
				var defer = $q.defer();
				(function() {
					$.getScript('/views/default/pl/fe/matter/enroll/event.js', function() {
						defer.resolve();
					});
				})();
				return defer.promise;
			}
		}
	}).when('/rest/pl/fe/matter/enroll/record', {
		templateUrl: '/views/default/pl/fe/matter/enroll/record.html?_=1',
		controller: 'ctrlRecord',
		resolve: {
			load: function($q) {
				var defer = $q.defer();
				(function() {
					$.getScript('/views/default/pl/fe/matter/enroll/record.js', function() {
						defer.resolve();
					});
				})();
				return defer.promise;
			}
		}
	}).when('/rest/pl/fe/matter/enroll/stat', {
		templateUrl: '/views/default/pl/fe/matter/enroll/stat.html?_=1',
		controller: 'ctrlStat',
		resolve: {
			load: function($q) {
				var defer = $q.defer();
				(function() {
					$.getScript('/views/default/pl/fe/matter/enroll/stat.js', function() {
						defer.resolve();
					});
				})();
				return defer.promise;
			}
		}
	}).when('/rest/pl/fe/matter/enroll/coin', {
		templateUrl: '/views/default/pl/fe/matter/enroll/coin.html?_=1',
		controller: 'ctrlCoin',
		resolve: {
			load: function($q) {
				var defer = $q.defer();
				(function() {
					$.getScript('/views/default/pl/fe/matter/enroll/coin.js', function() {
						defer.resolve();
					});
				})();
				return defer.promise;
			}
		}
	}).when('/rest/pl/fe/matter/enroll/running', {
		templateUrl: '/views/default/pl/fe/matter/enroll/running.html?_=1',
		controller: 'ctrlRunning',
		resolve: {
			load: function($q) {
				var defer = $q.defer();
				(function() {
					$.getScript('/views/default/pl/fe/matter/enroll/running.js', function() {
						defer.resolve();
					});
				})();
				return defer.promise;
			}
		}
	}).otherwise({
		templateUrl: '/views/default/pl/fe/matter/enroll/setting.html?_=1',
		controller: 'ctrlSetting',
		resolve: {
			load: function($q) {
				var defer = $q.defer();
				(function() {
					$.getScript('/views/default/pl/fe/matter/enroll/setting.js', function() {
						defer.resolve();
					});
				})();
				return defer.promise;
			}
		}
	});
	$locationProvider.html5Mode(true);
}]);
app.factory('Mp', function($q, http2) {
	var Mp = function() {};
	Mp.prototype.getAuthapis = function(id) {
		var _this = this,
			deferred = $q.defer(),
			promise = deferred.promise;
		if (_this.authapis !== undefined) {
			deferred.resolve(_this.authapis);
		} else {
			http2.get('/rest/mp/authapi/get?valid=Y', function(rsp) {
				_this.authapis = rsp.data;
				deferred.resolve(rsp.data);
			});
		}
		return promise;
	};
	return Mp;
});
app.controller('ctrlApp', ['$scope', '$location', 'http2', function($scope, $location, http2) {
	var ls = $location.search(),
		modifiedData = {};
	$scope.id = ls.id;
	$scope.siteid = ls.site;
	$scope.modified = false;
	$scope.submit = function() {
		http2.post('/rest/mp/app/enroll/update?aid=' + $scope.id, modifiedData, function(rsp) {
			$scope.modified = false;
			modifiedData = {};
		});
	};
	$scope.update = function(name) {
		if (['entry_rule'].indexOf(name) !== -1) {
			modifiedData[name] = encodeURIComponent($scope.app[name]);
		} else if (name === 'tags') {
			modifiedData.tags = $scope.app.tags.join(',');
		} else {
			modifiedData[name] = $scope.app[name];
		}
		$scope.modified = true;
	};
	http2.get('/rest/mp/app/enroll/get?aid=' + $scope.id + '&mpid=' + $scope.siteid, function(rsp) {
		var app;
		app = rsp.data;
		app.tags = (!app.tags || app.tags.length === 0) ? [] : app.tags.split(',');
		app.type = 'enroll';
		app.data_schemas = app.data_schemas && app.data_schemas.length ? JSON.parse(app.data_schemas) : [];
		$scope.persisted = angular.copy(app);
		$scope.app = app;
		$scope.url = 'http://' + location.host + '/rest/app/enroll?mpid=' + $scope.siteid + '&aid=' + $scope.id;
	});
}]);