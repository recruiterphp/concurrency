(ns knossos-onebip.core
  (:gen-class)
  (:require [knossos.core :as core]
            [clojure.java.io :as io]
            [knossos-onebip.model]
            [clojure-csv.core :refer [parse-csv]]
            [clojure.pprint :refer [pprint]]))

(defn history-from-file [path]
  (let [lines (-> (io/file path)
                  io/reader
                  parse-csv)]
    (map (fn [[_ process type f]]
           {:type (keyword type)
            :process (keyword process)
            :f (keyword f)
            :value nil})
         lines)))

(defn check-history [model-fn filename]
  (let [lock (model-fn)
        history (history-from-file filename)]
    (core/analysis lock history)))

(defn -main
  "Pass a model name (e.g. 'mongo-lock' and a CSV file.
  with the structure 'timestamp,process,type,f' (e.g. '1458232058222295,pp0,invoke|ok|fail,acquire')"
  [& args]
  (let [[model-name filename] args
        model-fn (ns-resolve (find-ns 'knossos-onebip.model)
                             (symbol model-name))
        analysis (check-history model-fn filename)]
    (pprint analysis)
    (if (:valid? analysis)
      (System/exit 0)
      (System/exit 1))))
