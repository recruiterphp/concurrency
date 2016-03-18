(defproject knossos-onebip "0.1.0-SNAPSHOT"
  :description "FIXME: write description"
  :url "http://example.com/FIXME"
  :license {:name "Eclipse Public License"
            :url "http://www.eclipse.org/legal/epl-v10.html"}
  :dependencies [[org.clojure/clojure "1.8.0"]
                 [knossos "0.2.4"]
                 [clojure-csv/clojure-csv "2.0.1"]]
  :main ^:skip-aot knossos-onebip.core
  :target-path "target/%s"
  :profiles {:uberjar {:aot :all}})
